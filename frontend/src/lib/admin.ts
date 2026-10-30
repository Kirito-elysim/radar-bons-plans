import type { StatutCollecte } from '../api/types'

export const LIBELLES_STATUTS: Record<
  StatutCollecte,
  { libelle: string; ton: 'ok' | 'attention' | 'critique' | 'neutre' }
> = {
  succes: { libelle: 'OK', ton: 'ok' },
  partiel: { libelle: 'Partiel', ton: 'attention' },
  vide: { libelle: '0 résultat', ton: 'critique' },
  erreur: { libelle: 'Erreur', ton: 'critique' },
  en_cours: { libelle: 'En cours', ton: 'neutre' },
}

const relatif = new Intl.RelativeTimeFormat('fr-FR', { numeric: 'auto' })

/** « il y a 3 heures », « hier »… à partir d'une date ISO. */
export function depuis(iso: string | null, maintenant = new Date()): string {
  if (!iso) return 'jamais'
  const secondes = (new Date(iso).getTime() - maintenant.getTime()) / 1000
  const unites: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ]
  for (const [unite, duree] of unites) {
    if (Math.abs(secondes) >= duree) return relatif.format(Math.round(secondes / duree), unite)
  }
  return "à l'instant"
}

/** Une collecte toutes les 6 h : au-delà de 7 h sans passage, le worker est probablement arrêté. */
export function enRetard(iso: string | null, actif: boolean, maintenant = new Date()): boolean {
  return actif && iso !== null && maintenant.getTime() - new Date(iso).getTime() > 7 * 3600 * 1000
}
