/// <reference types="vitest/config" />
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'
import { VitePWA } from 'vite-plugin-pwa'

// API Symfony en développement (service « api » du compose.yaml)
const cibleApi = process.env.VITE_API_CIBLE ?? 'http://localhost:8088'

export default defineConfig({
  server: {
    port: 5180,
    proxy: { '/api': { target: cibleApi, changeOrigin: true } },
  },
  plugins: [
    react(),
    VitePWA({
      registerType: 'prompt',
      injectRegister: false,
      includeAssets: ['favicon.svg', 'icones/apple-touch-icon.png'],
      manifest: {
        name: 'Radar Bons Plans',
        short_name: 'Radar',
        description: 'Tous les bons plans courses au même endroit, avec alertes avant expiration.',
        lang: 'fr',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        orientation: 'portrait',
        background_color: '#f6f3ec',
        theme_color: '#14213d',
        icons: [
          { src: '/icones/icone-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icones/icone-512.png', sizes: '512x512', type: 'image/png' },
          { src: '/icones/icone-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
        shortcuts: [
          { name: 'À saisir avant expiration', url: '/?urgence=1' },
          { name: 'Ajouter une vidéo', url: '/tiktok' },
        ],
      },
      workbox: {
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api\//],
        runtimeCaching: [
          {
            // Données de l'API : réseau d'abord, cache en secours hors ligne (jamais pour les écritures).
            urlPattern: ({ url, request }) => url.pathname.startsWith('/api/') && request.method === 'GET',
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api',
              networkTimeoutSeconds: 5,
              expiration: { maxEntries: 200, maxAgeSeconds: 7 * 24 * 3600 },
              cacheableResponse: { statuses: [200] },
            },
          },
        ],
      },
    }),
  ],
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: false,
  },
})
