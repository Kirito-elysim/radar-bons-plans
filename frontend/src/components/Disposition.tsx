import { NavLink, Outlet } from 'react-router'
import { useAuth } from '../auth/contexte'
import { Icone, type NomIcone } from './Icone'
import { MiseAJourPwa } from './MiseAJourPwa'

const ONGLETS: { vers: string; libelle: string; icone: NomIcone }[] = [
  { vers: '/', libelle: 'Bons plans', icone: 'accueil' },
  { vers: '/calendrier', libelle: 'Calendrier', icone: 'calendrier' },
  { vers: '/tiktok', libelle: 'Vidéo', icone: 'video' },
  { vers: '/economies', libelle: 'Économies', icone: 'tirelire' },
]

export function Disposition() {
  const { deconnecter } = useAuth()

  return (
    <div className="appli">
      <header className="entete">
        <div className="entete__marque">
          <img src="/favicon.svg" alt="" width={28} height={28} />
          <span>Radar</span>
        </div>
        <nav className="onglets onglets--haut" aria-label="Navigation principale">
          {ONGLETS.map((o) => (
            <NavLink key={o.vers} to={o.vers} end className="onglet">
              {o.libelle}
            </NavLink>
          ))}
        </nav>
        <button type="button" className="bouton-icone" onClick={deconnecter} title="Se déconnecter">
          <Icone nom="sortie" />
          <span className="visuellement-cache">Se déconnecter</span>
        </button>
      </header>

      <main className="contenu">
        <Outlet />
      </main>

      <nav className="onglets onglets--bas" aria-label="Navigation principale">
        {ONGLETS.map((o) => (
          <NavLink key={o.vers} to={o.vers} end className="onglet">
            <Icone nom={o.icone} taille={22} />
            <span>{o.libelle}</span>
          </NavLink>
        ))}
      </nav>
      <MiseAJourPwa />
    </div>
  )
}
