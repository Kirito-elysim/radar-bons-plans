import { createContext, useContext } from 'react'

export interface Auth {
  connecte: boolean
  connecter: (email: string, motDePasse: string) => Promise<void>
  deconnecter: () => void
}

export const Contexte = createContext<Auth | null>(null)

export function useAuth(): Auth {
  const auth = useContext(Contexte)
  if (!auth) throw new Error('useAuth doit être utilisé dans <AuthProvider>.')
  return auth
}
