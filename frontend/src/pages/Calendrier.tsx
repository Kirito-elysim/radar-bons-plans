import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { useCalendrier } from '../api/requetes'
import type { TypeBonPlan } from '../api/types'
import { Icone } from '../components/Icone'
import { decalerMois, grilleMois, moisCourant, parJour } from '../lib/calendrier'
import { aujourdhuiIso, dateLocale, LIBELLES_TYPES } from '../lib/format'

const JOURS = ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.']
const TYPES: TypeBonPlan[] = ['catalogue', 'remboursement', 'bon', 'code_promo', 'shopmium', 'tiktok']

export function Calendrier() {
  const [params, setParams] = useSearchParams()
  const mois = /^\d{4}-\d{2}$/.test(params.get('mois') ?? '') ? (params.get('mois') as string) : moisCourant()
  const [types, setTypes] = useState<TypeBonPlan[]>(['catalogue', 'remboursement'])
  const [jour, setJour] = useState<string | null>(null)
  const { data, isPending, isError, error } = useCalendrier(mois)

  const evenements = (data?.evenements ?? []).filter((e) => types.includes(e.type))
  const jours = parJour(evenements)
  const aujourdhui = aujourdhuiIso()
  const jourAffiche = jour?.startsWith(mois) ? jour : null
  const titreMois = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(
    dateLocale(`${mois}-01`),
  )

  const changerMois = (delta: number) => {
    setJour(null)
    setParams({ mois: decalerMois(mois, delta) })
  }

  return (
    <div className="page">
      <div className="page__tete page__tete--ligne">
        <h1 className="titre-mois">{titreMois}</h1>
        <div className="navigation-mois">
          <button
            type="button"
            className="bouton-icone"
            onClick={() => changerMois(-1)}
            aria-label="Mois précédent"
          >
            <Icone nom="gauche" />
          </button>
          <button
            type="button"
            className="bouton bouton--secondaire bouton--petit"
            onClick={() => setParams({})}
          >
            Aujourd'hui
          </button>
          <button
            type="button"
            className="bouton-icone"
            onClick={() => changerMois(1)}
            aria-label="Mois suivant"
          >
            <Icone nom="droite" />
          </button>
        </div>
      </div>

      <div className="puces" role="group" aria-label="Types affichés">
        {TYPES.map((t) => (
          <button
            key={t}
            type="button"
            className={`puce ${types.includes(t) ? 'active' : ''}`}
            aria-pressed={types.includes(t)}
            onClick={() => setTypes((l) => (l.includes(t) ? l.filter((x) => x !== t) : [...l, t]))}
          >
            {LIBELLES_TYPES[t]}
          </button>
        ))}
      </div>

      <p className="legende texte-doux">
        <span className="point point--debut" /> début <span className="point point--fin" /> fin
      </p>

      {isError && <p className="erreur">{error.message}</p>}
      <div className={`calendrier ${isPending ? 'calendrier--chargement' : ''}`}>
        {JOURS.map((j) => (
          <div key={j} className="calendrier__entete">
            {j}
          </div>
        ))}
        {grilleMois(mois).map((date, i) => {
          if (!date) return <div key={`vide-${i}`} className="calendrier__case calendrier__case--vide" />
          const liste = jours.get(date) ?? []
          const debuts = liste.filter((e) => e.nature === 'debut').length
          const fins = liste.length - debuts
          return (
            <button
              key={date}
              type="button"
              className={`calendrier__case ${date === aujourdhui ? 'aujourdhui' : ''} ${date === jourAffiche ? 'choisi' : ''} ${date < aujourdhui ? 'passe' : ''}`}
              onClick={() => setJour(date === jourAffiche ? null : date)}
              aria-label={`${dateLocale(date).getDate()} : ${debuts} début(s), ${fins} fin(s)`}
            >
              <span className="calendrier__numero">{dateLocale(date).getDate()}</span>
              <span className="calendrier__marques">
                {debuts > 0 && <span className="marque marque--debut">{debuts}</span>}
                {fins > 0 && <span className="marque marque--fin">{fins}</span>}
              </span>
            </button>
          )
        })}
      </div>

      <section className="liste-jour" aria-live="polite">
        {jourAffiche ? (
          <>
            <h2>
              {new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }).format(
                dateLocale(jourAffiche),
              )}
            </h2>
            {(jours.get(jourAffiche) ?? []).length === 0 && <p className="vide">Rien ce jour-là.</p>}
            <ul>
              {(jours.get(jourAffiche) ?? []).map((e) => (
                <li key={`${e.bonPlanId}-${e.nature}`} className="evenement">
                  <span className={`badge ${e.nature === 'fin' ? 'badge--critique' : 'badge--debut'}`}>
                    {e.nature === 'fin' ? 'Fin' : 'Début'}
                  </span>
                  <a href={e.url} target="_blank" rel="noopener noreferrer">
                    <strong>{e.enseigne ?? LIBELLES_TYPES[e.type]}</strong> — {e.titre}
                  </a>
                </li>
              ))}
            </ul>
          </>
        ) : (
          <p className="texte-doux">Touchez un jour pour voir les offres qui commencent ou se terminent.</p>
        )}
      </section>
    </div>
  )
}
