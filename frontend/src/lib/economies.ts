import type { StatistiquesEconomies } from '../api/types'
import { formaterEuros } from './format'

const AFFICHEES = 7

/** Pas « rond » de l'axe des montants (1, 2, 5 × 10^n). */
export function pasAxe(max: number, graduations = 3): number {
  if (max <= 0) return 1
  const brut = max / graduations
  const puissance = 10 ** Math.floor(Math.log10(brut))
  const facteur = [1, 2, 5, 10].find((f) => f * puissance >= brut) ?? 10
  return facteur * puissance
}

/** Regroupe au-delà des 7 premières enseignes dans « Autres » (une seule teinte : c'est une grandeur). */
export function regrouperEnseignes(
  liste: StatistiquesEconomies['parEnseigne'],
): StatistiquesEconomies['parEnseigne'] {
  if (liste.length <= AFFICHEES + 1) return liste
  const reste = liste.slice(AFFICHEES)
  const total = reste.reduce((somme, e) => somme + Number(e.total), 0)
  return [
    ...liste.slice(0, AFFICHEES),
    {
      enseigne: `Autres (${reste.length})`,
      total: total.toFixed(2),
      nombre: reste.reduce((n, e) => n + e.nombre, 0),
    },
  ]
}

/** Écart avec le mois précédent, en texte (« +2,10 € vs septembre »). */
export function ecartMois(mois: string, precedent: string): string | null {
  const ecart = Number(mois) - Number(precedent)
  if (Number(precedent) === 0 && Number(mois) === 0) return null
  const signe = ecart > 0 ? '+' : ecart < 0 ? '−' : '±'
  return `${signe}${formaterEuros(Math.abs(ecart))} vs mois dernier`
}
