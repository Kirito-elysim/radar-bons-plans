import { useState, type SyntheticEvent } from 'react'
import { ErreurApi } from '../api/client'
import {
  useBasculerSource,
  useCreateurs,
  useEnregistrerCreateur,
  useRelancerSource,
  useSourcesAdmin,
} from '../api/requetes'
import type { Createur, SourceAdmin } from '../api/types'
import { depuis, enRetard, LIBELLES_STATUTS } from '../lib/admin'

export function Admin() {
  const sources = useSourcesAdmin()

  return (
    <div className="page">
      <div className="page__tete">
        <h1>Administration</h1>
        <p className="texte-doux">
          État des connecteurs (collecte automatique toutes les 6 h) et créateurs suivis.
        </p>
      </div>

      {sources.isError && <p className="erreur">{sources.error.message}</p>}
      <section className="grille grille--sources" aria-label="Sources">
        {sources.data?.map((s) => (
          <CarteSource key={s.id} source={s} />
        ))}
      </section>

      <Createurs />
    </div>
  )
}

function CarteSource({ source: s }: { source: SourceAdmin }) {
  const basculer = useBasculerSource()
  const relancer = useRelancerSource()
  const statut = s.dernierStatut ? LIBELLES_STATUTS[s.dernierStatut] : null
  const retard = enRetard(s.dernierRun, s.actif && s.collectable)

  return (
    <article className={`carte carte--source ${s.actif ? '' : 'carte--inactive'}`}>
      <div className="carte__corps">
        <div className="carte__tete">
          <h2 className="carte__titre">{s.nom}</h2>
          {statut ? (
            <span className={`badge badge--statut-${statut.ton}`}>{statut.libelle}</span>
          ) : (
            <span className="badge">{s.collectable ? 'Jamais lancée' : 'Saisie manuelle'}</span>
          )}
        </div>
        <a className="texte-doux source__url" href={s.url} target="_blank" rel="noopener noreferrer">
          {s.url.replace(/^https?:\/\//, '')}
        </a>
        <dl className="source__chiffres">
          <div>
            <dt>Dernière collecte</dt>
            <dd className={retard ? 'texte-critique' : undefined} title={s.dernierRun ?? undefined}>
              {depuis(s.dernierRun)}
              {retard && ' · worker arrêté ?'}
            </dd>
          </div>
          <div>
            <dt>Reçus</dt>
            <dd>{s.dernierNbDeals ?? '–'}</dd>
          </div>
          <div>
            <dt>En cours</dt>
            <dd>{s.dealsEnCours}</dd>
          </div>
        </dl>
        {s.dernierErreur && <p className="source__erreur">{s.dernierErreur}</p>}
        {s.dernierStatut === 'vide' && (
          <p className="source__erreur">
            Le site a probablement changé : vérifiez les sélecteurs du connecteur.
          </p>
        )}
        {s.rapport && (
          <p className="succes" role="status">
            Relance : {s.rapport.recus} reçus, {s.rapport.nouveaux} nouveaux, {s.rapport.misAJour} mis à jour
            {s.rapport.pagesIgnorees.length > 0 && `, ${s.rapport.pagesIgnorees.length} page(s) ignorée(s)`}.
          </p>
        )}
        {relancer.error && <p className="erreur">{relancer.error.message}</p>}
        <div className="carte__actions">
          {s.collectable && (
            <label className="interrupteur">
              <input
                type="checkbox"
                checked={s.actif}
                disabled={basculer.isPending}
                onChange={() => basculer.mutate(s)}
              />
              Collecte active
            </label>
          )}
          {s.collectable && (
            <button
              type="button"
              className="bouton bouton--petit"
              disabled={relancer.isPending}
              onClick={() => relancer.mutate(s)}
            >
              {relancer.isPending ? 'Collecte…' : 'Relancer'}
            </button>
          )}
        </div>
      </div>
    </article>
  )
}

function Createurs() {
  const createurs = useCreateurs()
  const [nouveau, setNouveau] = useState('')
  const enregistrer = useEnregistrerCreateur()

  const ajouter = (e: SyntheticEvent) => {
    e.preventDefault()
    enregistrer.mutate({ pseudo: nouveau.trim(), plateforme: 'tiktok' }, { onSuccess: () => setNouveau('') })
  }
  const erreur = enregistrer.error instanceof ErreurApi ? enregistrer.error : null

  return (
    <section className="carte-formulaire bloc">
      <h2>Créateurs suivis</h2>
      <p className="texte-doux">
        Le lien en bio (Linktree, blog…) est lu par la collecte s'il est autorisé par son robots.txt. TikTok
        n'est jamais lu directement.
      </p>
      <ul className="liste-createurs">
        {createurs.data?.map((c) => (
          <LigneCreateur key={c.id} createur={c} />
        ))}
      </ul>
      <form onSubmit={ajouter} className="ajout-createur">
        <input
          value={nouveau}
          onChange={(e) => setNouveau(e.target.value)}
          placeholder="@pseudo TikTok"
          aria-label="Pseudo du créateur à suivre"
          required
        />
        <button type="submit" className="bouton bouton--secondaire" disabled={enregistrer.isPending}>
          Suivre
        </button>
      </form>
      {erreur && <p className="erreur">{erreur.message}</p>}
    </section>
  )
}

function LigneCreateur({ createur: c }: { createur: Createur }) {
  const enregistrer = useEnregistrerCreateur()
  const [lien, setLien] = useState(c.lienBio ?? '')
  const modifie = lien !== (c.lienBio ?? '')
  const erreur = enregistrer.error instanceof ErreurApi ? enregistrer.error : null

  const sauver = (e: SyntheticEvent) => {
    e.preventDefault()
    enregistrer.mutate({ id: c.id, pseudo: c.pseudo, lienBio: lien.trim() || null, actif: c.actif })
  }

  return (
    <li>
      <form onSubmit={sauver} className="ligne-createur">
        <a
          href={c.urlProfil ?? '#'}
          target="_blank"
          rel="noopener noreferrer"
          className="ligne-createur__pseudo"
        >
          @{c.pseudo}
        </a>
        <input
          type="url"
          inputMode="url"
          value={lien}
          onChange={(e) => setLien(e.target.value)}
          placeholder="https://linktr.ee/…"
          aria-label={`Lien en bio de @${c.pseudo}`}
        />
        <button type="submit" className="bouton bouton--petit" disabled={!modifie || enregistrer.isPending}>
          {enregistrer.isSuccess && !modifie ? '✓' : 'Enregistrer'}
        </button>
        <label className="interrupteur">
          <input
            type="checkbox"
            checked={c.actif}
            onChange={() =>
              enregistrer.mutate({ id: c.id, pseudo: c.pseudo, lienBio: c.lienBio, actif: !c.actif })
            }
          />
          Suivi
        </label>
      </form>
      {erreur && <small className="erreur">{erreur.pour('lienBio') ?? erreur.message}</small>}
    </li>
  )
}
