import type { StatistiquesEconomies } from '../api/types'
import { regrouperEnseignes } from '../lib/economies'
import { formaterEuros } from '../lib/format'

export function BarresEnseignes({ parEnseigne }: { parEnseigne: StatistiquesEconomies['parEnseigne'] }) {
  const lignes = regrouperEnseignes(parEnseigne)
  const max = Math.max(...lignes.map((e) => Number(e.total)), 0)

  return (
    <ul className="barres" aria-label="Économies par enseigne">
      {lignes.map((e) => (
        <li
          key={e.enseigne}
          className="barres__ligne"
          title={`${e.nombre} économie${e.nombre > 1 ? 's' : ''}`}
        >
          <span className="barres__nom">{e.enseigne}</span>
          <span className="barres__piste">
            <span
              className="barres__barre"
              style={{ width: `${max > 0 ? (Number(e.total) / max) * 100 : 0}%` }}
            />
          </span>
          <span className="barres__valeur">{formaterEuros(e.total)}</span>
        </li>
      ))}
    </ul>
  )
}
