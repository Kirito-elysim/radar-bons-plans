/** Icônes en ligne (traits), dessinées pour l'application. */
const CHEMINS = {
  radar: 'M5 18a9 9 0 0 1 14 0M8 15.5a5 5 0 0 1 8 0M12 13v.01',
  accueil: 'M4 11 12 4l8 7v8a1 1 0 0 1-1 1h-4v-6h-6v6H5a1 1 0 0 1-1-1z',
  calendrier: 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zM4 10h16M9 3v4M15 3v4',
  video: 'M4 6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zM16 10l4-2.5v9L16 14',
  tirelire: 'M5 11a7 6 0 0 1 12-3h2v3l1 1v3h-2a7 6 0 0 1-3 2.5V20h-3v-2h-2v2H7v-3A6 6 0 0 1 5 11zM12 7V5',
  etoile: 'm12 4 2.4 5 5.4.6-4 3.8 1.1 5.4L12 16.1l-4.9 2.7 1.1-5.4-4-3.8 5.4-.6z',
  lien: 'M14 5h5v5M19 5l-8 8M17 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h4',
  recherche: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-4-4',
  fermer: 'M6 6l12 12M18 6 6 18',
  coche: 'm5 12 5 5L19 7',
  gauche: 'm15 18-6-6 6-6',
  droite: 'm9 18 6-6-6-6',
  sortie: 'M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l4-4-4-4M14 12H4',
  reglages: 'M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1M15 4v4M9 10v4M17 16v4',
  horloge: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
} as const

export type NomIcone = keyof typeof CHEMINS

export function Icone({
  nom,
  taille = 20,
  plein = false,
}: {
  nom: NomIcone
  taille?: number
  plein?: boolean
}) {
  return (
    <svg
      width={taille}
      height={taille}
      viewBox="0 0 24 24"
      fill={plein ? 'currentColor' : 'none'}
      stroke="currentColor"
      strokeWidth={1.8}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d={CHEMINS[nom]} />
    </svg>
  )
}
