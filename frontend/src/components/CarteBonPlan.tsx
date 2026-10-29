import { useState } from 'react'
import { useBasculerFavori } from '../api/requetes'
import type { BonPlan } from '../api/types'
import { periode, valeurAffichee } from '../lib/format'
import { BadgeEcheance } from './BadgeEcheance'
import { Icone } from './Icone'
import { ModaleEconomie } from './ModaleEconomie'

export function CarteBonPlan({ bonPlan: b, compacte = false }: { bonPlan: BonPlan; compacte?: boolean }) {
  const favori = useBasculerFavori()
  const [profiter, setProfiter] = useState(false)
  const valeur = valeurAffichee(b)
  const qui = b.enseigne ?? b.marque
  const quand = periode(b)

  return (
    <article
      className={`carte ${compacte ? 'carte--compacte' : ''} ${b.urgence ? `carte--${b.urgence}` : ''}`}
    >
      {b.imageUrl && !compacte && (
        <div className="carte__visuel">
          <img src={b.imageUrl} alt="" loading="lazy" referrerPolicy="no-referrer" />
        </div>
      )}
      <div className="carte__corps">
        <div className="carte__tete">
          <span className="carte__qui">{qui ?? b.sourceNom}</span>
          <BadgeEcheance bonPlan={b} />
        </div>
        <h3 className="carte__titre">{b.titre}</h3>
        {valeur && <p className="carte__valeur">{valeur}</p>}
        <ul className="carte__meta">
          {b.sousTypeLibelle && !valeur?.includes('remboursé') && <li>{b.sousTypeLibelle}</li>}
          {b.categorie && !compacte && <li>{b.categorie}</li>}
          {b.condition && <li>{b.condition}</li>}
          {b.regionLibelle && <li>{b.regionLibelle}</li>}
          {b.exclusifAppli && <li>Exclusif appli</li>}
          {b.createur && <li>@{b.createur}</li>}
          {b.nouveau && <li className="carte__nouveau">Nouveau</li>}
        </ul>
        {quand && !compacte && <p className="carte__periode">{quand}</p>}
        <div className="carte__actions">
          <a
            className="bouton bouton--secondaire bouton--petit"
            href={b.url}
            target="_blank"
            rel="noopener noreferrer"
          >
            <Icone nom="lien" taille={16} />
            {b.type === 'catalogue' ? 'Feuilleter' : "Voir l'offre"}
          </a>
          <button type="button" className="bouton bouton--petit" onClick={() => setProfiter(true)}>
            J'en ai profité
          </button>
          <button
            type="button"
            className={`bouton-icone bouton-icone--favori ${b.favori ? 'actif' : ''}`}
            aria-pressed={b.favori}
            aria-label={b.favori ? 'Retirer des favoris' : 'Ajouter aux favoris'}
            title={b.favori ? 'Retirer des favoris' : 'Ajouter aux favoris (alertes J-3 et J-1)'}
            onClick={() => favori.mutate(b)}
          >
            <Icone nom="etoile" plein={b.favori} />
          </button>
        </div>
      </div>
      {profiter && <ModaleEconomie bonPlan={b} onFermer={() => setProfiter(false)} />}
    </article>
  )
}
