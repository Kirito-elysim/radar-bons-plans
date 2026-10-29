import type { ParametresRecherche } from '../api/requetes'
import { FILTRES_VIDES, type Filtres } from './filtres'
import { sectionParCle, type Section } from './sections'

/** Les filtres vivent dans l'URL : partageables, et le bouton « retour » fonctionne. */
export function filtresDepuisUrl(p: URLSearchParams): Filtres {
  const tri = p.get('tri')
  return {
    section: sectionParCle(p.get('section'))?.cle ?? null,
    enseigne: p.get('enseigne') ?? '',
    categorie: p.get('categorie') ?? '',
    q: p.get('q') ?? '',
    nouveau: p.get('nouveau') === '1',
    favoris: p.get('favoris') === '1',
    bientot: p.get('urgence') === '1',
    tri: tri === 'recents' || tri === 'valeur' ? tri : 'dateFin',
  }
}

export function filtresVersUrl(f: Filtres): URLSearchParams {
  const p = new URLSearchParams()
  if (f.section) p.set('section', f.section)
  if (f.enseigne) p.set('enseigne', f.enseigne)
  if (f.categorie) p.set('categorie', f.categorie)
  if (f.q) p.set('q', f.q)
  if (f.nouveau) p.set('nouveau', '1')
  if (f.favoris) p.set('favoris', '1')
  if (f.bientot) p.set('urgence', '1')
  if (f.tri !== FILTRES_VIDES.tri) p.set('tri', f.tri)
  return p
}

/** Paramètres d'API d'une section, combinés aux filtres communs. */
export function parametresSection(section: Section, f: Filtres): ParametresRecherche {
  return {
    ...section.params,
    enseigne: f.enseigne || undefined,
    categorie: f.categorie || undefined,
    q: f.q || undefined,
    nouveau: f.nouveau ? '1' : undefined,
    favoris: f.favoris ? '1' : undefined,
    expireSous: f.bientot ? '7' : undefined,
    tri: f.tri === 'dateFin' ? undefined : f.tri,
  }
}
