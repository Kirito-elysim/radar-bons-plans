// Filtres du tableau de bord (état reflété dans l'URL, voir filtresUrl.ts).

export interface Filtres {
  section: string | null
  enseigne: string
  categorie: string
  q: string
  nouveau: boolean
  favoris: boolean
  /** Uniquement ce qui se termine sous 7 jours. */
  bientot: boolean
  /** Inclure les catalogues régionaux des autres zones que la mienne. */
  toutesRegions: boolean
  tri: 'dateFin' | 'recents' | 'valeur'
}

export const FILTRES_VIDES: Filtres = {
  section: null,
  enseigne: '',
  categorie: '',
  q: '',
  nouveau: false,
  favoris: false,
  bientot: false,
  toutesRegions: false,
  tri: 'dateFin',
}
