import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'
import type {
  BonPlan,
  Calendrier,
  Collection,
  Economie,
  Preferences,
  Profil,
  PushConfiguration,
  RecapApercu,
  StatistiquesEconomies,
  TableauDeBord,
  TikTokApercu,
} from './types'

/** Paramètres de recherche des bons plans (voir GET /api/bons-plans). */
export type ParametresRecherche = Partial<
  Record<
    | 'type'
    | 'sousType'
    | 'enseigne'
    | 'categorie'
    | 'q'
    | 'nouveau'
    | 'favoris'
    | 'expireSous'
    | 'tri'
    | 'toutesRegions'
    | 'page'
    | 'itemsPerPage',
    string
  >
>

export function versQuery(params: ParametresRecherche): string {
  const q = new URLSearchParams()
  for (const [cle, valeur] of Object.entries(params) as [string, string | undefined][]) {
    if (valeur !== undefined && valeur !== '') q.set(cle, valeur)
  }
  const texte = q.toString()
  return texte ? `?${texte}` : ''
}

export const cles = {
  bonsPlans: (p: ParametresRecherche) => ['bonsPlans', p] as const,
  tableauDeBord: ['tableauDeBord'] as const,
  calendrier: (mois: string) => ['calendrier', mois] as const,
  preferences: ['preferences'] as const,
  economies: ['economies'] as const,
  moi: ['moi'] as const,
  push: ['push'] as const,
  recap: ['recap'] as const,
  statistiques: ['statistiquesEconomies'] as const,
}

export function useBonsPlans(params: ParametresRecherche, actif = true) {
  return useQuery({
    queryKey: cles.bonsPlans(params),
    queryFn: () => api<Collection<BonPlan>>(`/bons-plans${versQuery(params)}`),
    placeholderData: keepPreviousData,
    enabled: actif,
  })
}

export function useTableauDeBord() {
  return useQuery({ queryKey: cles.tableauDeBord, queryFn: () => api<TableauDeBord>('/tableau-de-bord') })
}

export function useCalendrier(mois: string) {
  return useQuery({
    queryKey: cles.calendrier(mois),
    queryFn: () => api<Calendrier>(`/calendrier?mois=${mois}`),
    placeholderData: keepPreviousData,
  })
}

export function usePreferences() {
  return useQuery({ queryKey: cles.preferences, queryFn: () => api<Preferences>('/preferences') })
}

export function useMoi(actif: boolean) {
  return useQuery({
    queryKey: cles.moi,
    queryFn: () => api<Profil>('/moi'),
    enabled: actif,
    staleTime: Infinity,
  })
}

export function useEconomies() {
  return useQuery({
    queryKey: cles.economies,
    queryFn: () => api<Collection<Economie>>('/economies?itemsPerPage=100'),
  })
}

/** Ajoute ou retire un favori, avec mise à jour optimiste des listes affichées. */
export function useBasculerFavori() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (b: BonPlan) =>
      b.favori ? api<null>(`/favoris/${b.id}`, 'DELETE') : api('/favoris', 'POST', { bonPlanId: b.id }),
    onMutate: async (b) => {
      await client.cancelQueries({ queryKey: ['bonsPlans'] })
      const basculer = (liste: BonPlan[]) =>
        liste.map((x) => (x.id === b.id ? { ...x, favori: !b.favori } : x))
      client.setQueriesData<Collection<BonPlan>>({ queryKey: ['bonsPlans'] }, (d) =>
        d ? { ...d, member: basculer(d.member) } : d,
      )
      client.setQueryData<TableauDeBord>(cles.tableauDeBord, (d) =>
        d ? { ...d, aSaisir: basculer(d.aSaisir) } : d,
      )
    },
    onSettled: () => {
      void client.invalidateQueries({ queryKey: ['bonsPlans'] })
      void client.invalidateQueries({ queryKey: cles.tableauDeBord })
    },
  })
}

export function useAjouterEconomie() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (e: {
      bonPlanId?: number
      libelle?: string
      montant?: string
      date?: string
      enseigne?: string
    }) => api<Economie>('/economies', 'POST', e),
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: cles.economies })
      void client.invalidateQueries({ queryKey: cles.statistiques })
      void client.invalidateQueries({ queryKey: cles.tableauDeBord })
    },
  })
}

export function useSupprimerEconomie() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api<null>(`/economies/${id}`, 'DELETE'),
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: cles.economies })
      void client.invalidateQueries({ queryKey: cles.statistiques })
      void client.invalidateQueries({ queryKey: cles.tableauDeBord })
    },
  })
}

export function useApercuTikTok() {
  return useMutation({ mutationFn: (url: string) => api<TikTokApercu>('/tiktok/apercu', 'POST', { url }) })
}

export function useEnregistrerTikTok() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (s: {
      url: string
      produit: string
      enseigne?: string
      economie?: string
      dateFin?: string
      condition?: string
    }) => api<BonPlan>('/tiktok/bons-plans', 'POST', s),
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: ['bonsPlans'] })
      void client.invalidateQueries({ queryKey: cles.tableauDeBord })
    },
  })
}

export function useEnregistrerPreferences() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (p: Partial<Preferences>) => {
      // La liste des régions est en lecture seule : inutile de la renvoyer.
      const corps = { ...p }
      delete corps.regionsDisponibles
      return api<Preferences>('/preferences', 'PATCH', corps)
    },
    onSuccess: (p) => {
      client.setQueryData(cles.preferences, p)
      void client.invalidateQueries({ queryKey: cles.tableauDeBord })
      void client.invalidateQueries({ queryKey: cles.recap })
    },
  })
}

export function usePushConfiguration() {
  return useQuery({ queryKey: cles.push, queryFn: () => api<PushConfiguration>('/push/configuration') })
}

export function useTestNotifications() {
  return useMutation({
    mutationFn: () => api<{ resultats: Record<string, string> }>('/notifications/test', 'POST', {}),
  })
}

export function useRecapApercu(actif: boolean) {
  return useQuery({ queryKey: cles.recap, queryFn: () => api<RecapApercu>('/recap/apercu'), enabled: actif })
}

export function useStatistiquesEconomies() {
  return useQuery({
    queryKey: cles.statistiques,
    queryFn: () => api<StatistiquesEconomies>('/economies/statistiques'),
  })
}

export function useModifierEconomie() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      ...e
    }: {
      id: number
      libelle?: string
      montant?: string
      date?: string
      enseigne?: string | null
    }) => api<Economie>(`/economies/${id}`, 'PATCH', e),
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: cles.economies })
      void client.invalidateQueries({ queryKey: cles.statistiques })
      void client.invalidateQueries({ queryKey: cles.tableauDeBord })
    },
  })
}
