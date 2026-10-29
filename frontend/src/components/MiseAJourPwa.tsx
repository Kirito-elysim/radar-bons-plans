import { useRegisterSW } from 'virtual:pwa-register/react'

/** Propose de recharger quand une nouvelle version de l'application est disponible. */
export function MiseAJourPwa() {
  const {
    needRefresh: [miseAJour, setMiseAJour],
    offlineReady: [horsLignePret, setHorsLignePret],
    updateServiceWorker,
  } = useRegisterSW()

  if (!miseAJour && !horsLignePret) return null

  return (
    <div className="toast" role="status">
      <span>{miseAJour ? 'Nouvelle version disponible.' : 'Radar fonctionne maintenant hors ligne.'}</span>
      {miseAJour && (
        <button type="button" className="bouton bouton--petit" onClick={() => void updateServiceWorker(true)}>
          Recharger
        </button>
      )}
      <button
        type="button"
        className="bouton-icone"
        onClick={() => {
          setMiseAJour(false)
          setHorsLignePret(false)
        }}
        aria-label="Fermer"
      >
        ×
      </button>
    </div>
  )
}
