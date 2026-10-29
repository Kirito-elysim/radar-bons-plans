import type { EvenementCalendrier } from '../api/types'

/** Cases d'une grille mensuelle commençant le lundi (null = case vide avant le 1er). */
export function grilleMois(mois: string): (string | null)[] {
  const [a, m] = mois.split('-').map(Number)
  const annee = a ?? 1970
  const index = (m ?? 1) - 1
  const premier = new Date(annee, index, 1)
  const nbJours = new Date(annee, index + 1, 0).getDate()
  const decalage = (premier.getDay() + 6) % 7 // lundi = 0
  const cases: (string | null)[] = Array.from({ length: decalage }, () => null)
  for (let j = 1; j <= nbJours; j++) cases.push(`${mois}-${String(j).padStart(2, '0')}`)
  while (cases.length % 7 !== 0) cases.push(null)
  return cases
}

export function decalerMois(mois: string, delta: number): string {
  const [a, m] = mois.split('-').map(Number)
  const d = new Date(a ?? 1970, (m ?? 1) - 1 + delta, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

export function moisCourant(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

export function parJour(evenements: EvenementCalendrier[]): Map<string, EvenementCalendrier[]> {
  const carte = new Map<string, EvenementCalendrier[]>()
  for (const e of evenements) {
    const liste = carte.get(e.date) ?? []
    liste.push(e)
    carte.set(e.date, liste)
  }
  return carte
}
