import { useState, type SyntheticEvent } from 'react'
import { Link } from 'react-router'
import { ErreurApi } from '../api/client'
import { useApercuTikTok, useEnregistrerTikTok } from '../api/requetes'
import { ENSEIGNES } from '../lib/sections'

/**
 * Ajout d'un bon plan repéré dans une vidéo TikTok : on colle le lien, l'appli récupère titre et
 * miniature via l'oEmbed public, puis on complète produit, enseigne, économie et date de fin.
 */
export function AjoutVideo() {
  const apercu = useApercuTikTok()
  const enregistrer = useEnregistrerTikTok()
  const [url, setUrl] = useState('')
  const [produit, setProduit] = useState('')
  const [enseigne, setEnseigne] = useState('')
  const [economie, setEconomie] = useState('')
  const [dateFin, setDateFin] = useState('')
  const [condition, setCondition] = useState('')

  const chercher = (e: SyntheticEvent) => {
    e.preventDefault()
    enregistrer.reset()
    apercu.mutate(url.trim())
  }

  const valider = (e: SyntheticEvent) => {
    e.preventDefault()
    enregistrer.mutate(
      {
        url: apercu.data?.url ?? url.trim(),
        produit,
        enseigne: enseigne || undefined,
        economie: economie || undefined,
        dateFin: dateFin || undefined,
        condition: condition || undefined,
      },
      {
        onSuccess: () => {
          setProduit('')
          setEconomie('')
          setCondition('')
        },
      },
    )
  }

  const erreurSaisie = enregistrer.error instanceof ErreurApi ? enregistrer.error : null
  // Si TikTok refuse l'aperçu, la saisie manuelle reste possible avec le lien complet.
  const formulaireOuvert = apercu.isSuccess || (apercu.isError && url.includes('/video/'))

  return (
    <div className="page page--etroite">
      <div className="page__tete">
        <h1>Ajouter une vidéo</h1>
        <p className="texte-doux">
          Collez le lien d'une vidéo TikTok (par exemple de @nelson_mrq ou @lejusticierdbp), puis complétez le
          bon plan.
        </p>
      </div>

      <form onSubmit={chercher} className="formulaire carte-formulaire">
        <label className="champ">
          <span>Lien de la vidéo</span>
          <input
            type="url"
            inputMode="url"
            placeholder="https://www.tiktok.com/@pseudo/video/…"
            required
            value={url}
            onChange={(e) => setUrl(e.target.value)}
          />
        </label>
        <button type="submit" className="bouton" disabled={apercu.isPending}>
          {apercu.isPending ? 'Récupération…' : 'Récupérer la vidéo'}
        </button>
        {apercu.isError && (
          <p className="erreur" role="alert">
            {apercu.error.message}
            {url.includes('/video/') && ' Vous pouvez quand même saisir le bon plan ci-dessous.'}
          </p>
        )}
      </form>

      {apercu.data && (
        <div className="apercu-video">
          {apercu.data.miniatureUrl && (
            <img src={apercu.data.miniatureUrl} alt="" referrerPolicy="no-referrer" />
          )}
          <div>
            <p className="apercu-video__auteur">
              @{apercu.data.auteurPseudo}
              {apercu.data.createurSuivi && <span className="badge badge--debut">Suivi</span>}
            </p>
            <p>{apercu.data.titre}</p>
          </div>
        </div>
      )}

      {formulaireOuvert && (
        <form onSubmit={valider} className="formulaire carte-formulaire">
          <label className="champ">
            <span>Produit *</span>
            <input
              required
              value={produit}
              onChange={(e) => setProduit(e.target.value)}
              placeholder="Lessive Skip 30 lavages"
            />
            {erreurSaisie?.pour('produit') && (
              <small className="erreur">{erreurSaisie.pour('produit')}</small>
            )}
          </label>
          <label className="champ">
            <span>Enseigne</span>
            <input
              list="enseignes"
              value={enseigne}
              onChange={(e) => setEnseigne(e.target.value)}
              placeholder="Lidl"
            />
            <datalist id="enseignes">
              {ENSEIGNES.map((e) => (
                <option key={e} value={e} />
              ))}
            </datalist>
          </label>
          <div className="formulaire__colonnes">
            <label className="champ">
              <span>Économie</span>
              <div className="champ__suffixe">
                <input
                  inputMode="decimal"
                  value={economie}
                  onChange={(e) => setEconomie(e.target.value)}
                  placeholder="2,50"
                />
                <span>€</span>
              </div>
              {erreurSaisie?.pour('economie') && (
                <small className="erreur">{erreurSaisie.pour('economie')}</small>
              )}
            </label>
            <label className="champ">
              <span>Date de fin</span>
              <input type="date" value={dateFin} onChange={(e) => setDateFin(e.target.value)} />
            </label>
          </div>
          <label className="champ">
            <span>Condition</span>
            <input
              value={condition}
              onChange={(e) => setCondition(e.target.value)}
              placeholder="dès 2 produits achetés"
            />
          </label>
          {erreurSaisie && !erreurSaisie.pour('produit') && !erreurSaisie.pour('economie') && (
            <p className="erreur">{erreurSaisie.message}</p>
          )}
          <button type="submit" className="bouton bouton--large" disabled={enregistrer.isPending}>
            {enregistrer.isPending ? 'Enregistrement…' : 'Enregistrer le bon plan'}
          </button>
          {enregistrer.isSuccess && (
            <p className="succes" role="status">
              « {enregistrer.data.titre} » ajouté. <Link to="/?section=tiktok">Voir la section TikTok</Link>
            </p>
          )}
        </form>
      )}
    </div>
  )
}
