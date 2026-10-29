import { useCallback } from 'react'
import { useSearchParams } from 'react-router'
import { usePreferences, useTableauDeBord } from '../api/requetes'
import { BandeauASaisir } from '../components/BandeauASaisir'
import { BarreFiltres } from '../components/BarreFiltres'
import type { Filtres } from '../lib/filtres'
import { Compteurs } from '../components/Compteurs'
import { SectionBonsPlans } from '../components/SectionBonsPlans'
import { filtresDepuisUrl, filtresVersUrl, parametresSection } from '../lib/filtresUrl'
import { CATEGORIES, SECTIONS } from '../lib/sections'

export function TableauDeBord() {
  const [recherche, setRecherche] = useSearchParams()
  const filtres = filtresDepuisUrl(recherche)
  const tableau = useTableauDeBord()
  const preferences = usePreferences()

  const changer = useCallback(
    (f: Filtres) => {
      setRecherche(filtresVersUrl(f), { replace: f.q !== filtres.q })
      if (f.section !== filtres.section) window.scrollTo({ top: 0, behavior: 'smooth' })
    },
    [setRecherche, filtres.q, filtres.section],
  )

  const sections = filtres.section ? SECTIONS.filter((s) => s.cle === filtres.section) : SECTIONS
  const sansFiltre = filtresVersUrl(filtres).toString() === ''

  return (
    <div className="page">
      <div className="page__tete">
        <h1>Bons plans du jour</h1>
        <p className="texte-doux">
          {new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }).format(
            new Date(),
          )}
          {tableau.data && tableau.data.nouveauxDepuisHier > 0 && (
            <>
              {' · '}
              <button
                type="button"
                className="bouton-lien"
                onClick={() => changer({ ...filtres, nouveau: true })}
              >
                {tableau.data.nouveauxDepuisHier} nouveaux depuis hier
              </button>
            </>
          )}
        </p>
      </div>

      {tableau.isError && <p className="erreur">{tableau.error.message}</p>}
      {tableau.data && sansFiltre && (
        <BandeauASaisir
          bonsPlans={tableau.data.aSaisir}
          sous7Jours={tableau.data.finissentSous7Jours}
          sous3Jours={tableau.data.finissentSous3Jours}
          onToutVoir={() => changer({ ...filtres, bientot: true })}
        />
      )}
      {tableau.data && sansFiltre && <Compteurs t={tableau.data} />}

      <BarreFiltres
        filtres={filtres}
        categories={CATEGORIES}
        enseignesSuivies={preferences.data?.enseignesSuivies ?? []}
        onChange={changer}
      />

      {sections.map((s) => (
        <SectionBonsPlans
          key={`${s.cle}-${filtresVersUrl(filtres).toString()}`}
          section={s}
          params={parametresSection(s, filtres)}
          deployee={filtres.section !== null}
          onVoirTout={() => changer({ ...filtres, section: s.cle })}
        />
      ))}
    </div>
  )
}
