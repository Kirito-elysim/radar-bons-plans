import { describe, expect, it } from 'vitest'
import { ecartMois, pasAxe, regrouperEnseignes } from './economies'

const n = (s: string | null) => s?.normalize('NFKC') ?? null

describe('pasAxe', () => {
  it.each([
    [0, 1],
    [7, 5],
    [12.5, 5],
    [48, 20],
    [230, 100],
  ])('max %s → pas %s', (max, pas) => {
    expect(pasAxe(max)).toBe(pas)
  })
})

describe('regrouperEnseignes', () => {
  const e = (enseigne: string, total: string) => ({ enseigne, total, nombre: 1 })

  it('garde jusqu’à 8 lignes telles quelles', () => {
    const liste = Array.from({ length: 8 }, (_, i) => e(`E${i}`, '1.00'))
    expect(regrouperEnseignes(liste)).toEqual(liste)
  })

  it('regroupe le reste dans « Autres »', () => {
    const liste = Array.from({ length: 10 }, (_, i) => e(`E${i}`, '1.10'))
    const resultat = regrouperEnseignes(liste)
    expect(resultat).toHaveLength(8)
    expect(resultat[7]).toEqual({ enseigne: 'Autres (3)', total: '3.30', nombre: 3 })
  })
})

describe('ecartMois', () => {
  it('compare au mois précédent', () => {
    expect(n(ecartMois('12.10', '10.00'))).toBe('+2,10 € vs mois dernier')
    expect(n(ecartMois('3.00', '10.00'))).toBe('−7,00 € vs mois dernier')
    expect(n(ecartMois('5.00', '5.00'))).toBe('±0,00 € vs mois dernier')
  })
  it('rien à dire sans économie', () => {
    expect(ecartMois('0.00', '0.00')).toBeNull()
  })
})
