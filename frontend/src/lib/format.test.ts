import { describe, expect, it } from 'vitest'
import { formaterEuros, libelleEcheance, periode, valeurAffichee } from './format'

// Intl insère des espaces insécables : NFKC les ramène à des espaces simples pour comparer.
const n = (s: string | null) => s?.normalize('NFKC') ?? null

describe('formaterEuros', () => {
  it('formate à la française', () => {
    expect(n(formaterEuros('2.50'))).toBe('2,50 €')
    expect(n(formaterEuros('0.5'))).toBe('0,50 €')
    expect(n(formaterEuros('11311.91'))).toBe('11 311,91 €')
  })
  it('arrondit les gros montants entiers', () => {
    expect(n(formaterEuros('200.00'))).toBe('200 €')
  })
  it('ignore les valeurs absentes ou invalides', () => {
    expect(formaterEuros(null)).toBe('')
    expect(formaterEuros('abc')).toBe('')
  })
})

describe('valeurAffichee', () => {
  it('privilégie le montant en euros', () => {
    expect(n(valeurAffichee({ valeurEuros: '8.99', valeurPourcent: '20.00', sousType: null }))).toBe('8,99 €')
  })
  it('affiche « 100 % remboursé » pour les offres intégralement remboursées', () => {
    expect(
      n(valeurAffichee({ valeurEuros: null, valeurPourcent: '100.00', sousType: 'rembourse_100' })),
    ).toBe('100 % remboursé')
  })
  it('affiche une réduction en pourcentage', () => {
    expect(n(valeurAffichee({ valeurEuros: null, valeurPourcent: '15.00', sousType: null }))).toBe('-15 %')
    expect(n(valeurAffichee({ valeurEuros: null, valeurPourcent: '2.50', sousType: null }))).toBe('-2,5 %')
  })
  it('renvoie null sans montant', () => {
    expect(valeurAffichee({ valeurEuros: null, valeurPourcent: null, sousType: null })).toBeNull()
  })
})

describe('libelleEcheance', () => {
  it.each([
    [null, null],
    [-1, 'Terminé'],
    [0, 'Dernier jour'],
    [1, 'J-1'],
    [12, 'J-12'],
  ])('%s jours → %s', (jours, attendu) => {
    expect(libelleEcheance(jours)).toBe(attendu)
  })
})

describe('periode', () => {
  it('signale une date de fin estimée', () => {
    expect(periode({ dateDebut: null, dateFin: '2026-11-01', dateFinEstimee: true })).toBe(
      'fin estimée le 1 novembre',
    )
  })
  it('affiche la période d’un catalogue à venir', () => {
    expect(periode({ dateDebut: '2099-11-03', dateFin: '2099-11-15', dateFinEstimee: false })).toBe(
      'du 3 nov. au 15 novembre',
    )
  })
  it('sans date de fin', () => {
    expect(periode({ dateDebut: null, dateFin: null, dateFinEstimee: false })).toBeNull()
  })
})
