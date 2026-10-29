import type { TableauDeBord } from '../api/types'
import { formaterEuros } from '../lib/format'

export function Compteurs({ t }: { t: TableauDeBord }) {
  return (
    <section className="compteurs" aria-label="Chiffres clés">
      <div className={`compteur ${t.finissentSous3Jours > 0 ? 'compteur--alerte' : ''}`}>
        <span className="compteur__valeur">{t.finissentSous3Jours}</span>
        <span className="compteur__libelle">finissent sous 3 jours</span>
      </div>
      <div className="compteur">
        <span className="compteur__valeur">{t.rembourses100EnCours}</span>
        <span className="compteur__libelle">produits 100 % remboursés</span>
      </div>
      <div className="compteur">
        <span className="compteur__valeur">{formaterEuros(t.economiePotentielle)}</span>
        <span className="compteur__libelle">d'économie potentielle repérée</span>
      </div>
      <div className="compteur compteur--gain">
        <span className="compteur__valeur">{formaterEuros(t.economiesMois)}</span>
        <span className="compteur__libelle">
          économisés ce mois · {formaterEuros(t.economiesTotal)} au total
        </span>
      </div>
    </section>
  )
}
