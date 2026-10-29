import { BrowserRouter, Navigate, Route, Routes } from 'react-router'
import { useAuth } from './auth/contexte'
import { Disposition } from './components/Disposition'
import { AjoutVideo } from './pages/AjoutVideo'
import { Calendrier } from './pages/Calendrier'
import { Connexion } from './pages/Connexion'
import { Economies } from './pages/Economies'
import { Preferences } from './pages/Preferences'
import { TableauDeBord } from './pages/TableauDeBord'

export function App() {
  const { connecte } = useAuth()

  if (!connecte) return <Connexion />

  return (
    <BrowserRouter>
      <Routes>
        <Route element={<Disposition />}>
          <Route index element={<TableauDeBord />} />
          <Route path="calendrier" element={<Calendrier />} />
          <Route path="tiktok" element={<AjoutVideo />} />
          <Route path="economies" element={<Economies />} />
          <Route path="reglages" element={<Preferences />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
