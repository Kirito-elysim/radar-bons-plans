import { useEconomies, useSupprimerEconomie, useTableauDeBord } from '../api/requetes'
import { Icone } from '../components/Icone'
import { formaterDate, formaterEuros } from '../lib/format'

/** Historique simple des économies (statistiques et export CSV : étape « suivi des économies »). */
export function Economies() {
  const economies = useEconomies()
  const tableau = useTableauDeBord()
  const supprimer = useSupprimerEconomie()

  return (
    <div className="page page--etroite">
      <div className="page__tete">
        <h1>Mes économies</h1>
        {tableau.data && (
          <p className="total-economies">
            <strong>{formaterEuros(tableau.data.economiesMois)}</strong> ce mois ·{' '}
            {formaterEuros(tableau.data.economiesTotal)} au total
          </p>
        )}
      </div>
      {economies.isError && <p className="erreur">{economies.error.message}</p>}
      {economies.data?.member.length === 0 && (
        <p className="vide">Aucune économie pour l'instant : utilisez « J'en ai profité » sur une offre.</p>
      )}
      <ul className="liste-economies">
        {economies.data?.member.map((e) => (
          <li key={e.id}>
            <div>
              <strong>{e.libelle}</strong>
              <span className="texte-doux">
                {formaterDate(e.date)}
                {e.enseigne && ` · ${e.enseigne}`}
              </span>
            </div>
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
    </div>
  )
}
