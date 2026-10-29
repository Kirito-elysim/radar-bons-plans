import { useState, type SyntheticEvent } from 'react'
import { ErreurApi } from '../api/client'
import { useAuth } from '../auth/contexte'

export function Connexion() {
  const { connecter } = useAuth()
  const [email, setEmail] = useState('')
  const [motDePasse, setMotDePasse] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  const envoyer = async (e: SyntheticEvent) => {
    e.preventDefault()
    setEnvoi(true)
    setErreur(null)
    try {
      await connecter(email, motDePasse)
    } catch (err) {
      setErreur(
        err instanceof ErreurApi && err.statut === 401
          ? 'Adresse e-mail ou mot de passe incorrect.'
          : err instanceof ErreurApi && err.statut === 429
            ? 'Trop de tentatives : patientez une minute.'
            : err instanceof Error
              ? err.message
              : 'Connexion impossible.',
      )
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="connexion">
      <div className="connexion__carte">
        <img src="/icones/icone-192.png" alt="" width={72} height={72} className="connexion__logo" />
        <h1>Radar Bons Plans</h1>
        <p className="texte-doux">
          Catalogues, remboursements, bons et codes promo : tout au même endroit, avant qu'ils n'expirent.
        </p>
        <form onSubmit={(e) => void envoyer(e)} className="formulaire">
          <label className="champ">
            <span>Adresse e-mail</span>
            <input
              type="email"
              autoComplete="username"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </label>
          <label className="champ">
            <span>Mot de passe</span>
            <input
              type="password"
              autoComplete="current-password"
              required
              value={motDePasse}
              onChange={(e) => setMotDePasse(e.target.value)}
            />
          </label>
          {erreur && (
            <p className="erreur" role="alert">
              {erreur}
            </p>
          )}
          <button type="submit" className="bouton bouton--large" disabled={envoi}>
            {envoi ? 'Connexion…' : 'Se connecter'}
          </button>
        </form>
      </div>
    </div>
  )
}
