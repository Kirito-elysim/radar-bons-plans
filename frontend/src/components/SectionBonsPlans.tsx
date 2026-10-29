import { useState } from 'react'
import { useBonsPlans, type ParametresRecherche } from '../api/requetes'
import type { Section } from '../lib/sections'
import { CarteBonPlan } from './CarteBonPlan'

const APERCU = 6
const PAR_PAGE = 24

interface Props {
  section: Section
  params: ParametresRecherche
  /** Section seule à l'écran : liste complète avec « Afficher plus ». */
  deployee: boolean
  onVoirTout: () => void
}

export function SectionBonsPlans({ section, params, deployee, onVoirTout }: Props) {
  const [pages, setPages] = useState(1)
  const parPage = deployee ? PAR_PAGE * pages : APERCU
  const { data, isPending, isError, error, isFetching } = useBonsPlans({
    ...params,
    itemsPerPage: String(parPage),
  })

  if (isPending) {
    return (
      <section className="section">
        <h2 className="section__titre">{section.titre}</h2>
        <div className="grille">
          {Array.from({ length: 3 }, (_, i) => (
            <div key={i} className="carte carte--squelette" aria-hidden="true" />
          ))}
        </div>
      </section>
    )
  }
  if (isError) return <p className="erreur">{error.message}</p>
  if (data.totalItems === 0 && !deployee) return null

  return (
    <section className="section" aria-labelledby={`titre-${section.cle}`}>
      <div className="section__tete">
        <div>
          <h2 id={`titre-${section.cle}`} className="section__titre">
            {section.titre} <span className="section__nombre">{data.totalItems}</span>
          </h2>
          <p className="texte-doux">{section.sousTitre}</p>
        </div>
        {!deployee && data.totalItems > APERCU && (
          <button type="button" className="bouton-lien" onClick={onVoirTout}>
            Tout voir
          </button>
        )}
      </div>
      {data.totalItems === 0 ? (
        <p className="vide">Aucune offre ne correspond à ces filtres.</p>
      ) : (
        <div className="grille">
          {data.member.map((b) => (
            <CarteBonPlan key={b.id} bonPlan={b} />
          ))}
        </div>
      )}
      {deployee && data.member.length < data.totalItems && (
        <button
          type="button"
          className="bouton bouton--secondaire bouton--large"
          disabled={isFetching}
          onClick={() => setPages((p) => p + 1)}
        >
          {isFetching ? 'Chargement…' : `Afficher plus (${data.totalItems - data.member.length} restantes)`}
        </button>
      )}
    </section>
  )
}
