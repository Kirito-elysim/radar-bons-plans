/* Gestion des notifications Web Push, importée par le service worker Workbox (voir vite.config.ts). */
self.addEventListener('push', (evenement) => {
  let donnees = {}
  try {
    donnees = evenement.data ? evenement.data.json() : {}
  } catch {
    donnees = { corps: evenement.data ? evenement.data.text() : '' }
  }
  evenement.waitUntil(
    self.registration.showNotification(donnees.titre || 'Radar Bons Plans', {
      body: donnees.corps || '',
      icon: '/icones/icone-192.png',
      badge: '/icones/icone-192.png',
      tag: donnees.tag || 'radar',
      lang: 'fr',
      data: { url: donnees.url || '/' },
    }),
  )
})

self.addEventListener('notificationclick', (evenement) => {
  evenement.notification.close()
  const cible = new URL(evenement.notification.data?.url || '/', self.location.origin).href
  evenement.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((fenetres) => {
      for (const fenetre of fenetres) {
        if (fenetre.url.startsWith(self.location.origin) && 'focus' in fenetre) {
          fenetre.navigate(cible)
          return fenetre.focus()
        }
      }
      return self.clients.openWindow(cible)
    }),
  )
})
