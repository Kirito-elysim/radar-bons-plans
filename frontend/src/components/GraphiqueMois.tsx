import { useState } from 'react'
import type { StatistiquesEconomies } from '../api/types'
import { pasAxe } from '../lib/economies'
import { dateLocale, formaterEuros } from '../lib/format'

const LARGEUR = 640
const HAUTEUR = 220
const MARGE = { haut: 24, droite: 8, bas: 28, gauche: 44 }
const ECART = 2 // espace de surface entre deux barres
const RAYON = 4

const moisCourt = new Intl.DateTimeFormat('fr-FR', { month: 'short' })
const moisLong = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' })

/** Chemin d'une barre arrondie en haut uniquement (la base reste posée sur l'axe). */
function barre(x: number, y: number, l: number, h: number): string {
  const r = Math.min(RAYON, l / 2, h)
  return `M${x},${y + h}V${y + r}Q${x},${y} ${x + r},${y}H${x + l - r}Q${x + l},${y} ${x + l},${y + r}V${y + h}Z`
}

/** Économies des 12 derniers mois : une seule série, barres verticales, infobulle au survol ou au clavier. */
export function GraphiqueMois({ parMois }: { parMois: StatistiquesEconomies['parMois'] }) {
  const [actif, setActif] = useState<number | null>(null)
  const valeurs = parMois.map((m) => Number(m.total))
  const max = Math.max(...valeurs, 0)
  const pas = pasAxe(max)
  const plafond = Math.max(pas * Math.ceil(max / pas), pas)
  const zoneL = LARGEUR - MARGE.gauche - MARGE.droite
  const zoneH = HAUTEUR - MARGE.haut - MARGE.bas
  const colonne = zoneL / parMois.length
  const largeurBarre = Math.max(colonne * 0.62, 4)
  const y = (v: number) => MARGE.haut + zoneH - (v / plafond) * zoneH
  const indiceMax = valeurs.indexOf(max)
  const graduations = Array.from({ length: Math.round(plafond / pas) + 1 }, (_, i) => i * pas)
  const survole = actif !== null ? parMois[actif] : undefined

  return (
    <figure className="graphique">
      <div className="graphique__zone">
        <svg
          viewBox={`0 0 ${LARGEUR} ${HAUTEUR}`}
          role="img"
          aria-label="Économies réalisées par mois sur 12 mois"
        >
          {graduations.map((g) => (
            <g key={g}>
              <line
                className="graphique__grille"
                x1={MARGE.gauche}
                x2={LARGEUR - MARGE.droite}
                y1={y(g)}
                y2={y(g)}
              />
              <text className="graphique__axe" x={MARGE.gauche - 8} y={y(g)} dy="0.32em" textAnchor="end">
                {formaterEuros(g).replace(/,00/, '')}
              </text>
            </g>
          ))}
          {parMois.map((m, i) => {
            const v = valeurs[i] ?? 0
            const xCol = MARGE.gauche + i * colonne
            const xBarre = xCol + (colonne - largeurBarre) / 2 + ECART / 2
            const h = (v / plafond) * zoneH
            const etiquette = i === indiceMax && v > 0
            return (
              <g key={m.mois}>
                {h > 0 && (
                  <path
                    className={`graphique__barre ${actif === i ? 'actif' : ''}`}
                    d={barre(xBarre, y(v), largeurBarre - ECART, h)}
                  />
                )}
                {etiquette && (
                  <text
                    className="graphique__valeur"
                    x={xBarre + (largeurBarre - ECART) / 2}
                    y={y(v) - 6}
                    textAnchor="middle"
                  >
                    {formaterEuros(v)}
                  </text>
                )}
                <text className="graphique__axe" x={xCol + colonne / 2} y={HAUTEUR - 8} textAnchor="middle">
                  {moisCourt.format(dateLocale(`${m.mois}-01`)).replace('.', '')}
                </text>
                {/* Zone de survol plus large que la barre (toute la colonne) */}
                <rect
                  className="graphique__cible"
                  x={xCol}
                  y={MARGE.haut}
                  width={colonne}
                  height={zoneH}
                  tabIndex={0}
                  aria-label={`${moisLong.format(dateLocale(`${m.mois}-01`))} : ${formaterEuros(v)}`}
                  onMouseEnter={() => setActif(i)}
                  onMouseLeave={() => setActif(null)}
                  onFocus={() => setActif(i)}
                  onBlur={() => setActif(null)}
                />
              </g>
            )
          })}
          <line
            className="graphique__base"
            x1={MARGE.gauche}
            x2={LARGEUR - MARGE.droite}
            y1={y(0)}
            y2={y(0)}
          />
        </svg>
        {survole && actif !== null && (
          <div
            className="infobulle"
            role="status"
            style={{ left: `${((MARGE.gauche + (actif + 0.5) * colonne) / LARGEUR) * 100}%` }}
          >
            <strong>{moisLong.format(dateLocale(`${survole.mois}-01`))}</strong>
            <span>
              {formaterEuros(survole.total)} · {survole.nombre} économie{survole.nombre > 1 ? 's' : ''}
            </span>
          </div>
        )}
      </div>
      <details className="graphique__tableau">
        <summary>Voir les données</summary>
        <table>
          <thead>
            <tr>
              <th scope="col">Mois</th>
              <th scope="col">Montant</th>
              <th scope="col">Économies</th>
            </tr>
          </thead>
          <tbody>
            {parMois.map((m) => (
              <tr key={m.mois}>
                <th scope="row">{moisLong.format(dateLocale(`${m.mois}-01`))}</th>
                <td>{formaterEuros(m.total)}</td>
                <td>{m.nombre}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </details>
    </figure>
  )
}
