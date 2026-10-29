import type { BonPlan, SousType, TypeBonPlan } from '../api/types'

const euros = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' })
const eurosRonds = new Intl.NumberFormat('fr-FR', {
  style: 'currency',
  currency: 'EUR',
  maximumFractionDigits: 0,
})

/** « 2.50 » → « 2,50 € » ; les montants entiers ≥ 100 sont arrondis (« 200 € »). */
export function formaterEuros(montant: string | number | null | undefined): string {
  if (montant === null || montant === undefined || montant === '') return ''
  const n = typeof montant === 'number' ? montant : Number(montant)
  if (!Number.isFinite(n)) return ''
  return Number.isInteger(n) && n >= 100 ? eurosRonds.format(n) : euros.format(n)
}

/** Valeur affichée sur une carte : « 8,99 € », « -20 % », « 100 % remboursé » ou null. */
export function valeurAffichee(
  b: Pick<BonPlan, 'valeurEuros' | 'valeurPourcent' | 'sousType'>,
): string | null {
  if (b.valeurEuros !== null) return formaterEuros(b.valeurEuros)
  if (b.valeurPourcent !== null) {
    const p = Number(b.valeurPourcent)
    const texte = `${Number.isInteger(p) ? p : String(p).replace('.', ',')} %`
    return b.sousType === 'rembourse_100' || p === 100 ? `${texte} remboursé` : `-${texte}`
  }
  return null
}

/** Badge d'échéance : « Dernier jour », « J-1 », « J-12 », « Terminé ». */
export function libelleEcheance(joursRestants: number | null): string | null {
  if (joursRestants === null) return null
  if (joursRestants < 0) return 'Terminé'
  if (joursRestants === 0) return 'Dernier jour'
  return `J-${joursRestants}`
}

const dateLongue = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long' })
const dateCourte = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' })

/** « 2026-11-15 » → Date locale à minuit (sans décalage UTC). */
export function dateLocale(iso: string): Date {
  const [a, m, j] = iso.split('-').map(Number)
  return new Date(a ?? 1970, (m ?? 1) - 1, j ?? 1)
}

export function formaterDate(iso: string | null, courte = false): string {
  if (!iso) return ''
  return (courte ? dateCourte : dateLongue).format(dateLocale(iso))
}

/** « jusqu'au 15 novembre » / « du 3 au 15 novembre » */
export function periode(b: Pick<BonPlan, 'dateDebut' | 'dateFin' | 'dateFinEstimee'>): string | null {
  if (!b.dateFin) return null
  const fin = formaterDate(b.dateFin)
  if (b.dateFinEstimee) return `fin estimée le ${fin}`
  if (b.dateDebut && b.dateDebut > new Date().toISOString().slice(0, 10)) {
    return `du ${formaterDate(b.dateDebut, true)} au ${fin}`
  }
  return `jusqu'au ${fin}`
}

export const LIBELLES_TYPES: Record<TypeBonPlan, string> = {
  catalogue: 'Catalogue',
  remboursement: 'Remboursement',
  bon: 'Bon de réduction',
  code_promo: 'Code promo',
  shopmium: 'Shopmium',
  tiktok: 'TikTok',
}

export const LIBELLES_SOUS_TYPES: Record<SousType, string> = {
  rembourse_100: '100 % remboursé',
  partiel: 'Partiel',
  bon_achat: "Bon d'achat",
  offre_essai: "Offre d'essai",
  offre_specifique: 'Offre spécifique',
}

/** Aujourd'hui au format AAAA-MM-JJ, heure locale. */
export function aujourdhuiIso(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
