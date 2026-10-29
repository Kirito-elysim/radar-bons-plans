import type { BonPlan } from '../api/types'
import { libelleEcheance } from '../lib/format'

/** Badge « J-x » : rouge sous 3 jours, orange sous 7 jours. */
export function BadgeEcheance({
  bonPlan,
}: {
  bonPlan: Pick<BonPlan, 'joursRestants' | 'urgence' | 'dateFinEstimee'>
}) {
  const libelle = libelleEcheance(bonPlan.joursRestants)
  if (!libelle) return null

  return (
    <span
      className={`badge badge--echeance ${bonPlan.urgence ? `badge--${bonPlan.urgence}` : ''}`}
      title={bonPlan.dateFinEstimee ? 'Date de fin estimée' : undefined}
    >
      {bonPlan.dateFinEstimee ? '≈ ' : ''}
      {libelle}
    </span>
  )
}
