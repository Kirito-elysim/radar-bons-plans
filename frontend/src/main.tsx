import '@fontsource-variable/bricolage-grotesque'
import '@fontsource-variable/inter'
import './styles/global.css'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { App } from './App'
import { ErreurApi } from './api/client'
import { AuthProvider } from './auth/AuthContext'

const client = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60_000,
      // Pas de nouvel essai sur une erreur « métier » (401, 404, 422).
      retry: (essais, erreur) =>
        !(erreur instanceof ErreurApi && erreur.statut >= 400 && erreur.statut < 500) && essais < 2,
    },
  },
})

const racine = document.getElementById('root')
if (!racine) throw new Error('Élément #root introuvable')

createRoot(racine).render(
  <StrictMode>
    <QueryClientProvider client={client}>
      <AuthProvider>
        <App />
      </AuthProvider>
    </QueryClientProvider>
  </StrictMode>,
)
