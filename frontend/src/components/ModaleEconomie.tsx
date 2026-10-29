import { useEffect, useRef, useState, type SyntheticEvent } from 'react'
import { ErreurApi } from '../api/client'
import { useAjouterEconomie } from '../api/requetes'
import type { BonPlan } from '../api/types'
import { aujourdhuiIso, formaterEuros } from '../lib/format'

/** « J'en ai profité » : enregistre une économie avec le montant pré-rempli, modifiable. */
export function ModaleEconomie({ bonPlan, onFermer }: { bonPlan: BonPlan; onFermer: () => void }) {
  const dialogue = useRef<HTMLDialogElement>(null)
  const ajouter = useAjouterEconomie()
  const [montant, setMontant] = useState(bonPlan.valeurEuros?.replace('.', ',') ?? '')
  const [date, setDate] = useState(aujourdhuiIso())
  const [succes, setSucces] = useState(false)

  // Pas de close() au démontage : en StrictMode il déclencherait onClose et refermerait aussitôt la modale.
  useEffect(() => {
    const d = dialogue.current
    if (d && !d.open) d.showModal()
  }, [])

  const envoyer = (e: SyntheticEvent) => {
    e.preventDefault()
    ajouter.mutate(
      { bonPlanId: bonPlan.id, montant: montant.trim() || undefined, date },
      {
        onSuccess: () => {
          setSucces(true)
          setTimeout(onFermer, 900)
        },
      },
    )
  }
  const erreur = ajouter.error instanceof ErreurApi ? ajouter.error : null

  return (
    <dialog ref={dialogue} className="modale" onClose={onFermer} aria-labelledby="titre-economie">
      <form onSubmit={envoyer} className="formulaire">
        <h2 id="titre-economie">J'en ai profité</h2>
        <p className="texte-doux">{bonPlan.titre}</p>
        <label className="champ">
          <span>Montant économisé</span>
          <div className="champ__suffixe">
            <input
              inputMode="decimal"
              value={montant}
              onChange={(e) => setMontant(e.target.value)}
              placeholder="0,00"
              autoFocus
              aria-invalid={Boolean(erreur?.pour('montant'))}
              required={bonPlan.valeurEuros === null}
            />
            <span>€</span>
          </div>
          {erreur?.pour('montant') && <small className="erreur">{erreur.pour('montant')}</small>}
        </label>
        <label className="champ">
          <span>Date</span>
          <input type="date" value={date} max={aujourdhuiIso()} onChange={(e) => setDate(e.target.value)} />
        </label>
        {erreur && !erreur.pour('montant') && <p className="erreur">{erreur.message}</p>}
        <div className="formulaire__actions">
          <button type="button" className="bouton bouton--secondaire" onClick={onFermer}>
            Annuler
          </button>
          <button type="submit" className="bouton" disabled={ajouter.isPending || succes}>
            {succes ? `+ ${formaterEuros(montant.replace(',', '.'))} ✓` : 'Enregistrer'}
          </button>
        </div>
      </form>
    </dialog>
  )
}
