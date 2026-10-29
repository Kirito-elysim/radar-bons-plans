import { useState } from 'react'
import { telecharger } from '../api/client'
import { useEconomies, useStatistiquesEconomies, useSupprimerEconomie } from '../api/requetes'
import type { Economie } from '../api/types'
import { BarresEnseignes } from '../components/BarresEnseignes'
import { GraphiqueMois } from '../components/GraphiqueMois'
import { Icone } from '../components/Icone'
import { ModaleSaisieEconomie } from '../components/ModaleSaisieEconomie'
import { ecartMois } from '../lib/economies'
import { aujourdhuiIso, dateLocale, formaterDate, formaterEuros } from '../lib/format'

type Periode = 'tout' | 'annee' | 'mois'

function bornes(periode: Periode): string {
  const auj = aujourdhuiIso()
  if (periode === 'annee') return `?du=${auj.slice(0, 4)}-01-01&au=${auj}`
  if (periode === 'mois') return `?du=${auj.slice(0, 7)}-01&au=${auj}`
  return ''
}

export function Economies() {
  const stats = useStatistiquesEconomies()
  const economies = useEconomies()
  const supprimer = useSupprimerEconomie()
  const [saisie, setSaisie] = useState<Economie | 'nouvelle' | null>(null)
  const [periode, setPeriode] = useState<Periode>('tout')
  const [erreurExport, setErreurExport] = useState<string | null>(null)

  const exporter = () => {
    setErreurExport(null)
    telecharger(`/economies/export.csv${bornes(periode)}`, 'economies-radar.csv').catch((e: unknown) => {
      setErreurExport(e instanceof Error ? e.message : 'Export impossible.')
    })
  }
  const s = stats.data

  return (
    <div className="page">
      <div className="page__tete page__tete--ligne">
        <h1>Mes économies</h1>
        <button type="button" className="bouton" onClick={() => setSaisie('nouvelle')}>
          + Ajouter
        </button>
      </div>

      {stats.isError && <p className="erreur">{stats.error.message}</p>}
      {s && (
        <section className="compteurs" aria-label="Totaux">
          <div className="compteur compteur--gain">
            <span className="compteur__valeur">{formaterEuros(s.totalMois)}</span>
            <span className="compteur__libelle">
              ce mois
              {ecartMois(s.totalMois, s.totalMoisPrecedent)
                ? ` · ${ecartMois(s.totalMois, s.totalMoisPrecedent) ?? ''}`
                : ''}
            </span>
          </div>
          <div className="compteur">
            <span className="compteur__valeur">{formaterEuros(s.totalAnnee)}</span>
            <span className="compteur__libelle">cette année</span>
          </div>
          <div className="compteur">
            <span className="compteur__valeur">{formaterEuros(s.total)}</span>
            <span className="compteur__libelle">
              au total · {s.nombre} économie{s.nombre > 1 ? 's' : ''}
            </span>
          </div>
          <div className="compteur">
            <span className="compteur__valeur">{formaterEuros(s.moyenneMensuelle)}</span>
            <span className="compteur__libelle">
              par mois en moyenne
              {s.meilleurMois &&
                ` · record : ${new Intl.DateTimeFormat('fr-FR', { month: 'long' }).format(dateLocale(`${s.meilleurMois}-01`))}`}
            </span>
          </div>
        </section>
      )}

      {s && s.nombre > 0 && (
        <div className="grille-economies">
          <section className="carte-formulaire bloc">
            <h2>12 derniers mois</h2>
            <GraphiqueMois parMois={s.parMois} />
          </section>
          <section className="carte-formulaire bloc">
            <h2>Par enseigne</h2>
            <BarresEnseignes parEnseigne={s.parEnseigne} />
            <h2 className="bloc__sous-titre">Par type de bon plan</h2>
            <BarresEnseignes
              parEnseigne={s.parType.map((t) => ({ enseigne: t.libelle, total: t.total, nombre: t.nombre }))}
            />
          </section>
        </div>
      )}

      <section className="bloc">
        <div className="section__tete">
          <h2>Historique</h2>
          <div className="export">
            <select
              value={periode}
              onChange={(e) => setPeriode(e.target.value as Periode)}
              aria-label="Période exportée"
            >
              <option value="tout">Tout</option>
              <option value="annee">Cette année</option>
              <option value="mois">Ce mois</option>
            </select>
            <button type="button" className="bouton bouton--secondaire bouton--petit" onClick={exporter}>
              Exporter en CSV
            </button>
          </div>
        </div>
        {erreurExport && <p className="erreur">{erreurExport}</p>}
        {economies.isError && <p className="erreur">{economies.error.message}</p>}
        {economies.data?.member.length === 0 && (
          <p className="vide">
            Aucune économie pour l'instant : utilisez « J'en ai profité » sur une offre, ou « + Ajouter ».
          </p>
        )}
        <ul className="liste-economies">
          {economies.data?.member.map((e) => (
            <li key={e.id}>
              <button
                type="button"
                className="liste-economies__corps"
                onClick={() => setSaisie(e)}
                title="Modifier"
              >
                <strong>{e.libelle}</strong>
                <span className="texte-doux">
                  {formaterDate(e.date)}
                  {e.enseigne && ` · ${e.enseigne}`}
                </span>
              </button>
              <span className="montant">+{formaterEuros(e.montant)}</span>
              <button
                type="button"
                className="bouton-icone"
                aria-label={`Supprimer « ${e.libelle} »`}
                onClick={() => {
                  if (confirm(`Supprimer « ${e.libelle} » ?`)) supprimer.mutate(e.id)
                }}
              >
                <Icone nom="fermer" taille={18} />
              </button>
            </li>
          ))}
        </ul>
      </section>

      {saisie && (
        <ModaleSaisieEconomie
          economie={saisie === 'nouvelle' ? undefined : saisie}
          onFermer={() => setSaisie(null)}
        />
      )}
    </div>
  )
}
