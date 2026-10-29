import type { BonPlan } from '../api/types'
import { CarteBonPlan } from './CarteBonPlan'

/** Bandeau défilant des offres qui se terminent dans les 7 jours. */
interface Props {
  bonsPlans: BonPlan[]
  sous7Jours: number
  sous3Jours: number
  onToutVoir: () => void
}

export function BandeauASaisir({ bonsPlans, sous7Jours, sous3Jours, onToutVoir }: Props) {
  if (bonsPlans.length === 0) return null

  return (
    <section className="bandeau" aria-labelledby="titre-bandeau">
      <div className="bandeau__tete">
        <h2 id="titre-bandeau">À saisir avant expiration</h2>
        <span className="texte-doux">
          {sous7Jours} offre{sous7Jours > 1 ? 's' : ''} sous 7 jours
          {sous3Jours > 0 && <strong className="texte-critique"> · {sous3Jours} sous 3 jours</strong>}
        </span>
        {sous7Jours > bonsPlans.length && (
          <button type="button" className="bouton-lien bandeau__lien" onClick={onToutVoir}>
            Tout voir
          </button>
        )}
      </div>
      <div className="bandeau__defilement">
        {bonsPlans.map((b) => (
          <CarteBonPlan key={b.id} bonPlan={b} compacte />
        ))}
      </div>
    </section>
  )
}
