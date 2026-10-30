import { BrowserRouter, Navigate, Route, Routes } from 'react-router'
import { useAuth } from './auth/contexte'
import { Disposition } from './components/Disposition'
import { MiseAJourPwa } from './components/MiseAJourPwa'
import { useMoi } from './api/requetes'
import { Admin } from './pages/Admin'
import { AjoutVideo } from './pages/AjoutVideo'
import { Calendrier } from './pages/Calendrier'
import { Connexion } from './pages/Connexion'
import { Economies } from './pages/Economies'
import { Preferences } from './pages/Preferences'
import { TableauDeBord } from './pages/TableauDeBord'

export function App() {
  const { connecte } = useAuth()
  const moi = useMoi(connecte)

  // Le service worker est enregistré dès l'écran de connexion (installation, hors ligne, push).
  if (!connecte) {
    return (
      <>
        <Connexion />
        <MiseAJourPwa />
      </>
    )
  }

  return (
    <BrowserRouter>
      <MiseAJourPwa />
      <Routes>
        <Route element={<Disposition />}>
          <Route index element={<TableauDeBord />} />
          <Route path="calendrier" element={<Calendrier />} />
          <Route path="tiktok" element={<AjoutVideo />} />
          <Route path="economies" element={<Economies />} />
          <Route path="reglages" element={<Preferences />} />
          <Route
            path="admin"
            element={
              // On attend le profil avant de décider : un lien direct vers /admin ne doit pas rediriger trop tôt.
              moi.isPending ? null : moi.data?.admin ? <Admin /> : <Navigate to="/" replace />
            }
          />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
