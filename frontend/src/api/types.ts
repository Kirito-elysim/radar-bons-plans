// Types des réponses de l'API Symfony (voir backend/src/ApiResource).

export type TypeBonPlan = 'catalogue' | 'remboursement' | 'bon' | 'code_promo' | 'shopmium' | 'tiktok'
export type SousType = 'rembourse_100' | 'partiel' | 'bon_achat' | 'offre_essai' | 'offre_specifique'
export type Urgence = 'critique' | 'proche' | null

export interface BonPlan {
  id: number
  type: TypeBonPlan
  typeLibelle: string
  sousType: SousType | null
  sousTypeLibelle: string | null
  titre: string
  enseigne: string | null
  marque: string | null
  description: string | null
  categorie: string | null
  valeurEuros: string | null
  valeurPourcent: string | null
  condition: string | null
  dateDebut: string | null
  dateFin: string | null
  dateFinEstimee: boolean
  joursRestants: number | null
  urgence: Urgence
  exclusifAppli: boolean
  region: string | null
  url: string
  imageUrl: string | null
  sourceCode: string
  sourceNom: string
  createur: string | null
  nouveau: boolean
  expire: boolean
  favori: boolean
  premiereVueLe: string
}

export interface Collection<T> {
  member: T[]
  totalItems: number
}

export interface TableauDeBord {
  finissentSous3Jours: number
  finissentSous7Jours: number
  rembourses100EnCours: number
  nouveauxDepuisHier: number
  economiePotentielle: string
  economiesMois: string
  economiesTotal: string
  parType: Record<TypeBonPlan, number>
  aSaisir: BonPlan[]
}

export interface EvenementCalendrier {
  date: string
  nature: 'debut' | 'fin'
  bonPlanId: number
  titre: string
  type: TypeBonPlan
  enseigne: string | null
  url: string
}

export interface Calendrier {
  mois: string
  evenements: EvenementCalendrier[]
}

export interface Economie {
  id: number
  libelle: string
  montant: string
  date: string
  enseigne: string | null
  bonPlanId: number | null
  creeLe: string
}

export interface Preferences {
  enseignesSuivies: string[]
  categories: string[]
  seuilAlerteJours: number
  canaux: ('push' | 'email' | 'telegram')[]
  heureRecap: string
  recapActif: boolean
  alertesRembourse100: boolean
  alertesNouveauCatalogue: boolean
  telegramChatId: string | null
}

export interface Profil {
  id: number
  email: string
  pseudo: string | null
  roles: string[]
  admin: boolean
}

export interface TikTokApercu {
  url: string
  titre: string | null
  auteurPseudo: string | null
  miniatureUrl: string | null
  idVideo: string | null
  createurSuivi: boolean
}

export interface Violation {
  propertyPath: string
  message: string
}

export interface PushConfiguration {
  disponible: boolean
  clePublique: string | null
  appareils: number
}

export interface RecapApercu {
  sujet: string
  intro: string
  resume: string[]
  sections: {
    titre: string
    lignes: {
      titre: string
      url: string
      qui: string | null
      valeur: string | null
      echeance: string | null
    }[]
  }[]
}
