import { useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api, expirationJeton, jeton } from '../api/client'
import { Contexte } from './contexte'

function jetonValide(): boolean {
  const valeur = jeton.lire()
  if (!valeur) return false
  const exp = expirationJeton(valeur)
  return exp === null || exp > Date.now()
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const client = useQueryClient()
  const [connecte, setConnecte] = useState(jetonValide)

  const deconnecter = useCallback(() => {
    jeton.ecrire(null)
    client.clear()
    setConnecte(false)
  }, [client])

  // L'API a refusé le jeton (expiré) : retour à l'écran de connexion.
  useEffect(
    () =>
      jeton.surDeconnexion(() => {
        client.clear()
        setConnecte(false)
      }),
    [client],
  )

  const connecter = useCallback(async (email: string, motDePasse: string) => {
    const { token } = await api<{ token: string }>('/connexion', 'POST', { email, motDePasse })
    jeton.ecrire(token)
    setConnecte(true)
  }, [])

  const valeur = useMemo(() => ({ connecte, connecter, deconnecter }), [connecte, connecter, deconnecter])

  return <Contexte.Provider value={valeur}>{children}</Contexte.Provider>
}
