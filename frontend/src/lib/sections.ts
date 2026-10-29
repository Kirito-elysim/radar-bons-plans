import type { ParametresRecherche } from '../api/requetes'
import type { TypeBonPlan } from '../api/types'

export interface Section {
  cle: string
  titre: string
  sousTitre: string
  params: ParametresRecherche
  type: TypeBonPlan
}

/** Sections du tableau de bord, dans l'ordre d'affichage. */
export const SECTIONS: Section[] = [
  {
    cle: 'catalogues',
    titre: 'Catalogues',
    sousTitre: 'En cours et à venir',
    type: 'catalogue',
    params: { type: 'catalogue' },
  },
  {
    cle: 'rembourses-100',
    titre: '100 % remboursés',
    sousTitre: 'Satisfait ou remboursé, produits offerts',
    type: 'remboursement',
    params: { type: 'remboursement', sousType: 'rembourse_100' },
  },
  {
    cle: 'remboursements',
    titre: 'Remboursements partiels',
    sousTitre: "ODR, bons d'achat, offres d'essai",
    type: 'remboursement',
    params: { type: 'remboursement', sousType: 'partiel,bon_achat,offre_essai,offre_specifique' },
  },
  {
    cle: 'bons',
    titre: 'Bons de réduction',
    sousTitre: 'À imprimer ou en remboursement',
    type: 'bon',
    params: { type: 'bon' },
  },
  {
    cle: 'shopmium',
    titre: 'Shopmium',
    sousTitre: "Exclusif dans l'appli",
    type: 'shopmium',
    params: { type: 'shopmium' },
  },
  {
    cle: 'codes',
    titre: 'Codes promo',
    sousTitre: 'Codes et bons plans en ligne',
    type: 'code_promo',
    params: { type: 'code_promo' },
  },
  {
    cle: 'tiktok',
    titre: 'TikTok',
    sousTitre: 'Repérés dans les vidéos',
    type: 'tiktok',
    params: { type: 'tiktok' },
  },
]

/** Enseignes proposées dans le filtre (prioritaires du cahier des charges). */
export const ENSEIGNES = [
  'Carrefour',
  'Carrefour Market',
  'Intermarché',
  'Leclerc',
  'Lidl',
  'Auchan',
  'Super U',
  'Aldi',
  'Netto',
  'Action',
]

export function sectionParCle(cle: string | null): Section | undefined {
  return SECTIONS.find((s) => s.cle === cle)
}

/** Catégories connues des sources (Anti-crise, Shopmium, Radins). */
export const CATEGORIES = [
  'Alimentation & boissons',
  'Épicerie salée',
  'Épicerie sucrée',
  'Produits frais',
  'Boissons',
  'Hygiène et beauté',
  'Santé, beauté & hygiène',
  "Produits d'entretien",
  'Entretien et maison',
  'Puériculture',
  'Petit électroménager',
  'Gros électroménager',
  'Multimédia',
  'Téléphonie',
  'Bricolage, jardin & animaux',
  'Sport, loisirs & jeux',
  'Auto & moto',
  'Fournitures scolaires & bureau',
  'Code promo',
  'Bon plan',
  'Autre',
]
