import type { Violation } from './types'

const CLE_JETON = 'radar.jeton'

/** Erreur renvoyée par l'API, avec les violations de validation éventuelles. */
export class ErreurApi extends Error {
  readonly statut: number
  readonly violations: Violation[]

  constructor(message: string, statut: number, violations: Violation[] = []) {
    super(message)
    this.name = 'ErreurApi'
    this.statut = statut
    this.violations = violations
  }

  /** Message de validation pour un champ donné. */
  pour(champ: string): string | undefined {
    return this.violations.find((v) => v.propertyPath === champ)?.message
  }
}

type Ecouteur = () => void
const ecouteursDeconnexion = new Set<Ecouteur>()

export const jeton = {
  lire(): string | null {
    try {
      return localStorage.getItem(CLE_JETON)
    } catch {
      return null
    }
  },
  ecrire(valeur: string | null): void {
    try {
      if (valeur === null) localStorage.removeItem(CLE_JETON)
      else localStorage.setItem(CLE_JETON, valeur)
    } catch {
      // Stockage indisponible (navigation privée) : la session durera le temps de l'onglet.
    }
  },
  /** Prévient l'application quand l'API refuse le jeton (expiré, révoqué). */
  surDeconnexion(ecouteur: Ecouteur): () => void {
    ecouteursDeconnexion.add(ecouteur)
    return () => ecouteursDeconnexion.delete(ecouteur)
  },
}

/** Date d'expiration (ms) lue dans la charge utile du JWT, sans vérification de signature. */
export function expirationJeton(valeur: string): number | null {
  try {
    const charge = valeur.split('.')[1]
    if (!charge) return null
    const json = JSON.parse(atob(charge.replace(/-/g, '+').replace(/_/g, '/'))) as { exp?: unknown }
    return typeof json.exp === 'number' ? json.exp * 1000 : null
  } catch {
    return null
  }
}

type Methode = 'GET' | 'POST' | 'PATCH' | 'DELETE'

export async function api<T>(chemin: string, methode: Methode = 'GET', corps?: unknown): Promise<T> {
  const entetes: Record<string, string> = { Accept: 'application/ld+json' }
  const valeurJeton = jeton.lire()
  if (valeurJeton) entetes.Authorization = `Bearer ${valeurJeton}`
  if (corps !== undefined) {
    entetes['Content-Type'] = methode === 'PATCH' ? 'application/merge-patch+json' : 'application/ld+json'
  }

  let reponse: Response
  try {
    reponse = await fetch(`/api${chemin}`, {
      method: methode,
      headers: entetes,
      body: corps !== undefined ? JSON.stringify(corps) : undefined,
    })
  } catch {
    throw new ErreurApi('Connexion impossible : vérifiez votre réseau.', 0)
  }

  if (reponse.status === 204) return undefined as T

  const texte = await reponse.text()
  const donnees: unknown = texte ? JSON.parse(texte) : null

  if (!reponse.ok) {
    if (reponse.status === 401 && chemin !== '/connexion') {
      jeton.ecrire(null)
      ecouteursDeconnexion.forEach((e) => {
        e()
      })
    }
    throw versErreur(reponse.status, donnees)
  }

  return donnees as T
}

function versErreur(statut: number, donnees: unknown): ErreurApi {
  const d = (donnees ?? {}) as { detail?: unknown; message?: unknown; violations?: unknown }
  const violations = Array.isArray(d.violations) ? (d.violations as Violation[]) : []
  const message =
    violations[0]?.message ??
    (typeof d.detail === 'string' ? d.detail : undefined) ??
    (typeof d.message === 'string' ? d.message : undefined) ??
    (statut === 404 ? 'Introuvable.' : 'Une erreur est survenue, réessayez.')
  return new ErreurApi(message, statut, violations)
}

/** Télécharge un fichier protégé par le jeton (ex. export CSV) et le propose à l'enregistrement. */
export async function telecharger(chemin: string, nomParDefaut: string): Promise<void> {
  const valeurJeton = jeton.lire()
  const reponse = await fetch(`/api${chemin}`, {
    headers: valeurJeton ? { Authorization: `Bearer ${valeurJeton}` } : {},
  })
  if (!reponse.ok) throw new ErreurApi('Export impossible, réessayez.', reponse.status)

  const nom =
    /filename="?([^";]+)"?/.exec(reponse.headers.get('Content-Disposition') ?? '')?.[1] ?? nomParDefaut
  const url = URL.createObjectURL(await reponse.blob())
  const lien = document.createElement('a')
  lien.href = url
  lien.download = nom
  document.body.append(lien)
  lien.click()
  lien.remove()
  URL.revokeObjectURL(url)
}
