import { useEffect, useState } from 'react'
import type { Filtres } from '../lib/filtres'
import { FILTRES_VIDES } from '../lib/filtres'
import { ENSEIGNES, SECTIONS } from '../lib/sections'
import { Icone } from './Icone'

interface Props {
  filtres: Filtres
  categories: string[]
  enseignesSuivies: string[]
  onChange: (f: Filtres) => void
}

export function BarreFiltres({ filtres, categories, enseignesSuivies, onChange }: Props) {
  // Recherche texte appliquée après une courte pause de frappe.
  const [texte, setTexte] = useState(filtres.q)
  const [qConnu, setQConnu] = useState(filtres.q)
  if (filtres.q !== qConnu) {
    // Filtres modifiés ailleurs (« Effacer », bouton retour) : on resynchronise le champ.
    setQConnu(filtres.q)
    setTexte(filtres.q)
  }
  useEffect(() => {
    if (texte === filtres.q) return
    const minuterie = setTimeout(() => onChange({ ...filtres, q: texte }), 300)
    return () => clearTimeout(minuterie)
  }, [texte, filtres, onChange])

  const enseignes = [...new Set([...enseignesSuivies, ...ENSEIGNES])]
  const actifs =
    filtres.enseigne ||
    filtres.categorie ||
    filtres.q ||
    filtres.nouveau ||
    filtres.favoris ||
    filtres.bientot ||
    filtres.toutesRegions ||
    filtres.section

  return (
    <section className="filtres" aria-label="Filtres">
      <label className="recherche">
        <Icone nom="recherche" taille={18} />
        <input
          type="search"
          placeholder="Rechercher un produit, une marque…"
          value={texte}
          onChange={(e) => setTexte(e.target.value)}
          aria-label="Recherche"
        />
      </label>

      <div className="puces" role="group" aria-label="Type d'offre">
        <button
          type="button"
          className={`puce ${filtres.section === null ? 'active' : ''}`}
          onClick={() => onChange({ ...filtres, section: null })}
        >
          Tout
        </button>
        {SECTIONS.map((s) => (
          <button
            key={s.cle}
            type="button"
            className={`puce ${filtres.section === s.cle ? 'active' : ''}`}
            onClick={() => onChange({ ...filtres, section: filtres.section === s.cle ? null : s.cle })}
          >
            {s.titre}
          </button>
        ))}
      </div>

      <div className="filtres__ligne">
        <select
          value={filtres.enseigne}
          onChange={(e) => onChange({ ...filtres, enseigne: e.target.value })}
          aria-label="Enseigne"
        >
          <option value="">Toutes les enseignes</option>
          {enseignes.map((e) => (
            <option key={e} value={e}>
              {e}
            </option>
          ))}
        </select>
        <select
          value={filtres.categorie}
          onChange={(e) => onChange({ ...filtres, categorie: e.target.value })}
          aria-label="Catégorie"
        >
          <option value="">Toutes les catégories</option>
          {categories.map((c) => (
            <option key={c} value={c}>
              {c}
            </option>
          ))}
        </select>
        <select
          value={filtres.tri}
          onChange={(e) => onChange({ ...filtres, tri: e.target.value as Filtres['tri'] })}
          aria-label="Tri"
        >
          <option value="dateFin">Fin la plus proche</option>
          <option value="recents">Plus récents</option>
          <option value="valeur">Plus gros montant</option>
        </select>
        <label className="interrupteur">
          <input
            type="checkbox"
            checked={filtres.nouveau}
            onChange={(e) => onChange({ ...filtres, nouveau: e.target.checked })}
          />
          Nouveau depuis hier
        </label>
        <label className="interrupteur">
          <input
            type="checkbox"
            checked={filtres.bientot}
            onChange={(e) => onChange({ ...filtres, bientot: e.target.checked })}
          />
          Finit sous 7 jours
        </label>
        <label className="interrupteur">
          <input
            type="checkbox"
            checked={filtres.favoris}
            onChange={(e) => onChange({ ...filtres, favoris: e.target.checked })}
          />
          Mes favoris
        </label>
        <label
          className="interrupteur"
          title="Afficher aussi les catalogues « Exclus Nord », « IDF »… des autres régions"
        >
          <input
            type="checkbox"
            checked={filtres.toutesRegions}
            onChange={(e) => onChange({ ...filtres, toutesRegions: e.target.checked })}
          />
          Toutes les régions
        </label>
        {actifs && (
          <button type="button" className="bouton-lien" onClick={() => onChange(FILTRES_VIDES)}>
            Effacer les filtres
          </button>
        )}
      </div>
    </section>
  )
}
