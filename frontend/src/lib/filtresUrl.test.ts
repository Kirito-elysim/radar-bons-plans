import { describe, expect, it } from 'vitest'
import { FILTRES_VIDES } from './filtres'
import { filtresDepuisUrl, filtresVersUrl, parametresSection } from './filtresUrl'
import { sectionParCle } from './sections'

describe('filtres dans l’URL', () => {
  it('aller-retour sans perte', () => {
    const f = {
      ...FILTRES_VIDES,
      section: 'rembourses-100',
      enseigne: 'Lidl',
      q: 'lessive',
      nouveau: true,
      bientot: true,
      toutesRegions: true,
      tri: 'valeur' as const,
    }
    expect(filtresDepuisUrl(filtresVersUrl(f))).toEqual(f)
  })

  it('URL vide = filtres par défaut, valeurs inconnues ignorées', () => {
    expect(filtresVersUrl(FILTRES_VIDES).toString()).toBe('')
    expect(filtresDepuisUrl(new URLSearchParams('section=inconnue&tri=nimporte'))).toEqual(FILTRES_VIDES)
  })

  it('combine les paramètres de la section et les filtres communs', () => {
    const section = sectionParCle('rembourses-100')
    if (!section) throw new Error('section manquante')

    expect(parametresSection(section, { ...FILTRES_VIDES, favoris: true, bientot: true, q: 'lait' })).toEqual(
      {
        type: 'remboursement',
        sousType: 'rembourse_100',
        enseigne: undefined,
        categorie: undefined,
        q: 'lait',
        nouveau: undefined,
        favoris: '1',
        expireSous: '7',
        tri: undefined,
      },
    )
  })
})
