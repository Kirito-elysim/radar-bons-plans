import { afterEach, describe, expect, it, vi } from 'vitest'
import { api, ErreurApi, expirationJeton, jeton } from './client'

function reponse(statut: number, corps?: unknown): Response {
  return new Response(corps === undefined ? null : JSON.stringify(corps), { status: statut })
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('client API', () => {
  it('envoie le jeton et le bon type de contenu', async () => {
    jeton.ecrire('abc')
    const fetchMock = vi.fn().mockResolvedValue(reponse(200, { ok: true }))
    vi.stubGlobal('fetch', fetchMock)

    await api('/preferences', 'PATCH', { heureRecap: '06:30' })

    expect(fetchMock).toHaveBeenCalledWith('/api/preferences', {
      method: 'PATCH',
      headers: {
        Accept: 'application/ld+json',
        Authorization: 'Bearer abc',
        'Content-Type': 'application/merge-patch+json',
      },
      body: '{"heureRecap":"06:30"}',
    })
  })

  it('expose les violations de validation', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        reponse(422, {
          violations: [{ propertyPath: 'montant', message: 'Montant invalide (ex. 2,50).' }],
        }),
      ),
    )

    const erreur = await api('/economies', 'POST', {}).catch((e: unknown) => e)

    expect(erreur).toBeInstanceOf(ErreurApi)
    expect((erreur as ErreurApi).pour('montant')).toBe('Montant invalide (ex. 2,50).')
    expect((erreur as ErreurApi).statut).toBe(422)
  })

  it('déconnecte quand le jeton est refusé', async () => {
    jeton.ecrire('expire')
    const deconnexion = vi.fn()
    const retirer = jeton.surDeconnexion(deconnexion)
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reponse(401, { message: 'Expired JWT Token' })))

    await expect(api('/moi')).rejects.toBeInstanceOf(ErreurApi)

    expect(deconnexion).toHaveBeenCalledOnce()
    expect(jeton.lire()).toBeNull()
    retirer()
  })

  it('erreur réseau lisible', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    await expect(api('/moi')).rejects.toThrow('Connexion impossible : vérifiez votre réseau.')
  })

  it('lit l’expiration du JWT', () => {
    const charge = btoa(JSON.stringify({ exp: 1_900_000_000 })).replace(/=+$/, '')
    expect(expirationJeton(`x.${charge}.y`)).toBe(1_900_000_000_000)
    expect(expirationJeton('pas-un-jwt')).toBeNull()
  })
})
