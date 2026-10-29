import { useEffect, useRef, useState, type SyntheticEvent } from 'react'
import { ErreurApi } from '../api/client'
import { useAjouterEconomie, useModifierEconomie } from '../api/requetes'
import type { Economie } from '../api/types'
import { aujourdhuiIso } from '../lib/format'
import { ENSEIGNES } from '../lib/sections'

/** Ajout d'une économie libre, ou modification d'une économie existante. */
export function ModaleSaisieEconomie({ economie, onFermer }: { economie?: Economie; onFermer: () => void }) {
  const dialogue = useRef<HTMLDialogElement>(null)
  const ajouter = useAjouterEconomie()
  const modifier = useModifierEconomie()
  const [libelle, setLibelle] = useState(economie?.libelle ?? '')
  const [montant, setMontant] = useState(economie?.montant.replace('.', ',') ?? '')
  const [date, setDate] = useState(economie?.date ?? aujourdhuiIso())
  const [enseigne, setEnseigne] = useState(economie?.enseigne ?? '')
  const mutation = economie ? modifier : ajouter

  // Pas de close() au démontage : en StrictMode il déclencherait onClose et refermerait la modale.
  useEffect(() => {
    const d = dialogue.current
    if (d && !d.open) d.showModal()
  }, [])

  const envoyer = (e: SyntheticEvent) => {
    e.preventDefault()
    const corps = {
      libelle: libelle.trim(),
      montant: montant.trim(),
      date,
      enseigne: enseigne.trim() || undefined,
    }
    if (economie)
      modifier.mutate(
        { id: economie.id, ...corps, enseigne: corps.enseigne ?? null },
        { onSuccess: onFermer },
      )
    else ajouter.mutate(corps, { onSuccess: onFermer })
  }
  const erreur = mutation.error instanceof ErreurApi ? mutation.error : null

  return (
    <dialog ref={dialogue} className="modale" onClose={onFermer} aria-labelledby="titre-saisie-economie">
      <form onSubmit={envoyer} className="formulaire">
        <h2 id="titre-saisie-economie">{economie ? "Modifier l'économie" : 'Ajouter une économie'}</h2>
        <label className="champ">
          <span>Libellé</span>
          <input
            required
            value={libelle}
            onChange={(e) => setLibelle(e.target.value)}
            placeholder="Promo en magasin"
          />
          {erreur?.pour('libelle') && <small className="erreur">{erreur.pour('libelle')}</small>}
        </label>
        <div className="formulaire__colonnes">
          <label className="champ">
            <span>Montant</span>
            <div className="champ__suffixe">
              <input
                required
                inputMode="decimal"
                value={montant}
                onChange={(e) => setMontant(e.target.value)}
                placeholder="0,00"
                aria-invalid={Boolean(erreur?.pour('montant'))}
              />
              <span>€</span>
            </div>
            {erreur?.pour('montant') && <small className="erreur">{erreur.pour('montant')}</small>}
          </label>
          <label className="champ">
            <span>Date</span>
            <input
              type="date"
              required
              value={date}
              max={aujourdhuiIso()}
              onChange={(e) => setDate(e.target.value)}
            />
          </label>
        </div>
        <label className="champ">
          <span>Enseigne</span>
          <input list="enseignes-economie" value={enseigne} onChange={(e) => setEnseigne(e.target.value)} />
          <datalist id="enseignes-economie">
            {ENSEIGNES.map((e) => (
              <option key={e} value={e} />
            ))}
          </datalist>
        </label>
        {erreur && !erreur.violations.length && <p className="erreur">{erreur.message}</p>}
        <div className="formulaire__actions">
          <button type="button" className="bouton bouton--secondaire" onClick={onFermer}>
            Annuler
          </button>
          <button type="submit" className="bouton" disabled={mutation.isPending}>
            Enregistrer
          </button>
        </div>
      </form>
    </dialog>
  )
}
