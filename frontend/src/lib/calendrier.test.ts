import { describe, expect, it } from 'vitest'
import { decalerMois, grilleMois } from './calendrier'

describe('grilleMois', () => {
  it('commence le lundi et complète les semaines', () => {
    const cases = grilleMois('2026-11') // le 1er novembre 2026 est un dimanche
    expect(cases.slice(0, 7)).toEqual([null, null, null, null, null, null, '2026-11-01'])
    expect(cases.length % 7).toBe(0)
    expect(cases.filter(Boolean)).toHaveLength(30)
  })
  it('gère février bissextile', () => {
    expect(grilleMois('2028-02').filter(Boolean)).toHaveLength(29)
  })
})

describe('decalerMois', () => {
  it('passe d’une année à l’autre', () => {
    expect(decalerMois('2026-12', 1)).toBe('2027-01')
    expect(decalerMois('2026-01', -1)).toBe('2025-12')
  })
})
