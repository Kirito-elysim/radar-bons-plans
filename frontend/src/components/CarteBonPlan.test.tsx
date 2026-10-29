import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { BonPlan } from '../api/types'
import { CarteBonPlan } from './CarteBonPlan'

const bonPlan: BonPlan = {
  id: 42,
  type: 'remboursement',
  typeLibelle: 'Offre de remboursement',
  sousType: 'rembourse_100',
  sousTypeLibelle: '100 % remboursé',
  titre: 'Pack de Lait Viva Satisfait ou 100% Remboursé',
  enseigne: null,
  marque: 'Candia',
  description: null,
  categorie: 'Alimentation & boissons',
  valeurEuros: null,
  valeurPourcent: '100.00',
  condition: null,
  dateDebut: null,
  dateFin: '2026-10-31',
  dateFinEstimee: false,
  joursRestants: 2,
  urgence: 'critique',
  exclusifAppli: false,
  region: null,
  regionLibelle: null,
  url: 'https://anti-crise.fr/les-offres-de-remboursement/candia/',
  imageUrl: null,
  sourceCode: 'anti_crise_remboursement',
  sourceNom: 'Anti-crise.fr',
  createur: null,
  nouveau: true,
  expire: false,
  favori: false,
  premiereVueLe: '2026-10-29T07:40:00+01:00',
}

function afficher(b: BonPlan = bonPlan) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return render(
    <QueryClientProvider client={client}>
      <CarteBonPlan bonPlan={b} />
    </QueryClientProvider>,
  )
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('CarteBonPlan', () => {
  it('affiche les informations essentielles et renvoie vers la source', () => {
    afficher()

    expect(screen.getByRole('heading', { name: bonPlan.titre })).toBeInTheDocument()
    expect(screen.getByText('Candia')).toBeInTheDocument()
    expect(screen.getByText('J-2')).toHaveClass('badge--critique')
    expect(screen.getByText(/100\s%\sremboursé/)).toHaveClass('carte__valeur')
    const lien = screen.getByRole('link', { name: /Voir l'offre/ })
    expect(lien).toHaveAttribute('href', bonPlan.url)
    expect(lien).toHaveAttribute('rel', 'noopener noreferrer')
  })

  it('ajoute aux favoris', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValue(new Response(JSON.stringify({ bonPlanId: 42 }), { status: 201 }))
    vi.stubGlobal('fetch', fetchMock)
    afficher()

    await userEvent.click(screen.getByRole('button', { name: 'Ajouter aux favoris' }))

    expect(fetchMock).toHaveBeenCalledWith(
      '/api/favoris',
      expect.objectContaining({ method: 'POST', body: '{"bonPlanId":42}' }),
    )
  })
})
