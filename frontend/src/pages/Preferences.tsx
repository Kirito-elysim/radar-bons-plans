import { useEffect, useState, type SyntheticEvent } from 'react'
import { ErreurApi } from '../api/client'
import {
  useEnregistrerPreferences,
  usePreferences,
  usePushConfiguration,
  useRecapApercu,
  useTestNotifications,
} from '../api/requetes'
import type { Preferences as Prefs } from '../api/types'
import { activerPush, appareilAbonne, desactiverPush, pushSupporte } from '../lib/push'
import { ENSEIGNES } from '../lib/sections'

const CANAUX: { valeur: Prefs['canaux'][number]; libelle: string; aide: string }[] = [
  {
    valeur: 'push',
    libelle: 'Notifications sur cet appareil',
    aide: 'Web Push : à activer sur chaque téléphone ou ordinateur.',
  },
  { valeur: 'email', libelle: 'E-mail', aide: 'Récap complet avec tous les liens.' },
  { valeur: 'telegram', libelle: 'Telegram', aide: 'Message du bot Radar dans votre discussion.' },
]

export function Preferences() {
  const prefs = usePreferences()
  if (prefs.isPending) return <p className="texte-doux">Chargement…</p>
  if (prefs.isError) return <p className="erreur">{prefs.error.message}</p>
  return <FormulairePreferences initiales={prefs.data} />
}

function FormulairePreferences({ initiales }: { initiales: Prefs }) {
  const enregistrer = useEnregistrerPreferences()
  const [p, setP] = useState(initiales)
  const [enregistre, setEnregistre] = useState(false)
  const modifier = (changement: Partial<Prefs>) => {
    setEnregistre(false)
    setP((actuel) => ({ ...actuel, ...changement }))
  }
  const basculer = <T,>(liste: T[], valeur: T) =>
    liste.includes(valeur) ? liste.filter((v) => v !== valeur) : [...liste, valeur]

  const envoyer = (e: SyntheticEvent) => {
    e.preventDefault()
    enregistrer.mutate(p, { onSuccess: () => setEnregistre(true) })
  }
  const erreur = enregistrer.error instanceof ErreurApi ? enregistrer.error : null

  return (
    <div className="page page--etroite">
      <div className="page__tete">
        <h1>Réglages</h1>
        <p className="texte-doux">Choisissez vos enseignes, vos alertes et l'heure du récap quotidien.</p>
      </div>

      <form onSubmit={envoyer} className="formulaire">
        <fieldset className="carte-formulaire bloc">
          <legend>Enseignes suivies</legend>
          <p className="texte-doux">Alerte « nouveau catalogue » et priorité dans le bandeau.</p>
          <div className="puces puces--multiligne">
            {[...new Set([...ENSEIGNES, ...p.enseignesSuivies])].map((e) => (
              <button
                key={e}
                type="button"
                className={`puce ${p.enseignesSuivies.includes(e) ? 'active' : ''}`}
                aria-pressed={p.enseignesSuivies.includes(e)}
                onClick={() => modifier({ enseignesSuivies: basculer(p.enseignesSuivies, e) })}
              >
                {e}
              </button>
            ))}
          </div>
        </fieldset>

        <fieldset className="carte-formulaire bloc">
          <legend>Alertes</legend>
          <label className="interrupteur interrupteur--ligne">
            <input
              type="checkbox"
              checked={p.alertesRembourse100}
              onChange={(e) => modifier({ alertesRembourse100: e.target.checked })}
            />
            Offres 100 % remboursées qui se terminent (en plus des favoris)
          </label>
          <label className="interrupteur interrupteur--ligne">
            <input
              type="checkbox"
              checked={p.alertesNouveauCatalogue}
              onChange={(e) => modifier({ alertesNouveauCatalogue: e.target.checked })}
            />
            Nouveau catalogue d'une enseigne suivie
          </label>
          <label className="champ">
            <span>Prévenir à J-{p.seuilAlerteJours} (et la veille)</span>
            <input
              type="range"
              min={1}
              max={7}
              value={p.seuilAlerteJours}
              onChange={(e) => modifier({ seuilAlerteJours: Number(e.target.value) })}
            />
            {erreur?.pour('seuilAlerteJours') && (
              <small className="erreur">{erreur.pour('seuilAlerteJours')}</small>
            )}
          </label>
        </fieldset>

        <fieldset className="carte-formulaire bloc">
          <legend>Récap quotidien</legend>
          <label className="interrupteur interrupteur--ligne">
            <input
              type="checkbox"
              checked={p.recapActif}
              onChange={(e) => modifier({ recapActif: e.target.checked })}
            />
            Recevoir le récap chaque matin
          </label>
          <label className="champ">
            <span>Heure d'envoi (heure de Paris)</span>
            <input
              type="time"
              step={300}
              value={p.heureRecap}
              disabled={!p.recapActif}
              onChange={(e) => modifier({ heureRecap: e.target.value })}
            />
            {erreur?.pour('heureRecap') && <small className="erreur">{erreur.pour('heureRecap')}</small>}
          </label>
        </fieldset>

        <fieldset className="carte-formulaire bloc">
          <legend>Canaux</legend>
          {CANAUX.map((c) => (
            <label key={c.valeur} className="interrupteur interrupteur--ligne">
              <input
                type="checkbox"
                checked={p.canaux.includes(c.valeur)}
                onChange={() => modifier({ canaux: basculer(p.canaux, c.valeur) })}
              />
              <span>
                {c.libelle}
                <small className="texte-doux bloc__aide">{c.aide}</small>
              </span>
            </label>
          ))}
          {p.canaux.includes('push') && <ActivationPush />}
          {p.canaux.includes('telegram') && (
            <label className="champ">
              <span>Identifiant de discussion Telegram</span>
              <input
                inputMode="numeric"
                value={p.telegramChatId ?? ''}
                placeholder="123456789"
                onChange={(e) => modifier({ telegramChatId: e.target.value.trim() || null })}
              />
              <small className="texte-doux">
                Écrivez « /start » au bot Radar, puis demandez l'identifiant à l'administrateur (commande
                app:telegram:discussions).
              </small>
              {erreur?.pour('telegramChatId') && (
                <small className="erreur">{erreur.pour('telegramChatId')}</small>
              )}
            </label>
          )}
        </fieldset>

        {erreur && !erreur.violations.length && <p className="erreur">{erreur.message}</p>}
        <div className="barre-enregistrer">
          <button type="submit" className="bouton bouton--large" disabled={enregistrer.isPending}>
            {enregistrer.isPending ? 'Enregistrement…' : enregistre ? 'Enregistré ✓' : 'Enregistrer'}
          </button>
        </div>
      </form>

      <TestEtApercu />
    </div>
  )
}

function ActivationPush() {
  const config = usePushConfiguration()
  const [abonne, setAbonne] = useState<boolean | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [encours, setEncours] = useState(false)

  useEffect(() => {
    void appareilAbonne().then(setAbonne)
  }, [])

  if (!pushSupporte()) {
    return (
      <p className="texte-doux">
        Ce navigateur ne gère pas les notifications push. Sur iPhone, installez d'abord Radar sur l'écran
        d'accueil (Partager → Sur l'écran d'accueil).
      </p>
    )
  }
  if (config.data && !config.data.disponible) {
    return (
      <p className="texte-doux">
        Le serveur n'a pas encore de clés VAPID : notifications push indisponibles.
      </p>
    )
  }

  const action = async () => {
    setEncours(true)
    setErreur(null)
    try {
      if (abonne) await desactiverPush()
      else if (config.data?.clePublique) await activerPush(config.data.clePublique)
      setAbonne(await appareilAbonne())
      void config.refetch()
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Activation impossible.')
    } finally {
      setEncours(false)
    }
  }

  return (
    <div className="activation-push">
      <button
        type="button"
        className="bouton bouton--secondaire"
        disabled={encours || !config.data}
        onClick={() => void action()}
      >
        {abonne ? 'Désactiver sur cet appareil' : 'Activer sur cet appareil'}
      </button>
      {config.data && (
        <small className="texte-doux">
          {config.data.appareils} appareil{config.data.appareils > 1 ? 's' : ''} abonné
          {config.data.appareils > 1 ? 's' : ''}
        </small>
      )}
      {erreur && <small className="erreur">{erreur}</small>}
    </div>
  )
}

function TestEtApercu() {
  const test = useTestNotifications()
  const [voir, setVoir] = useState(false)
  const apercu = useRecapApercu(voir)

  return (
    <section className="carte-formulaire bloc">
      <h2>Vérifier</h2>
      <div className="formulaire__actions formulaire__actions--gauche">
        <button
          type="button"
          className="bouton bouton--secondaire"
          disabled={test.isPending}
          onClick={() => test.mutate()}
        >
          {test.isPending ? 'Envoi…' : 'Envoyer une notification de test'}
        </button>
        <button type="button" className="bouton bouton--secondaire" onClick={() => setVoir((v) => !v)}>
          {voir ? "Masquer l'aperçu" : 'Aperçu du récap'}
        </button>
      </div>
      {test.error && <p className="erreur">{test.error.message}</p>}
      {test.data && (
        <ul className="resultats-test">
          {Object.entries(test.data.resultats).map(([canal, resultat]) => (
            <li key={canal}>
              <strong>{canal}</strong> :{' '}
              <span className={resultat === 'envoyé' ? 'succes' : 'texte-doux'}>{resultat}</span>
            </li>
          ))}
        </ul>
      )}
      {voir && apercu.data && (
        <div className="apercu-recap">
          <h3>{apercu.data.sujet}</h3>
          <ul>
            {apercu.data.resume.map((r) => (
              <li key={r}>{r}</li>
            ))}
          </ul>
          {apercu.data.sections
            .filter((s) => s.lignes.length > 0)
            .map((s) => (
              <div key={s.titre}>
                <h4>{s.titre}</h4>
                <ul>
                  {s.lignes.map((l) => (
                    <li key={l.url + l.titre}>
                      <a href={l.url} target="_blank" rel="noopener noreferrer">
                        {[l.qui, l.titre, l.valeur, l.echeance].filter(Boolean).join(' · ')}
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
        </div>
      )}
    </section>
  )
}
