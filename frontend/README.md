# Radar Bons Plans — front

PWA React + Vite + TypeScript. L'API Symfony doit tourner (`make api`, port 8088) : Vite y relaie `/api`.

```bash
npm install
npm run dev        # http://localhost:5180
npm test           # Vitest
npm run lint       # ESLint
npm run build      # build de production + service worker (dist/)
npm run icones     # régénère les icônes PWA dans public/icones/
```

Structure : `src/api` (client JWT, types, requêtes TanStack Query), `src/pages`, `src/components`, `src/lib` (formatage, filtres, calendrier).
