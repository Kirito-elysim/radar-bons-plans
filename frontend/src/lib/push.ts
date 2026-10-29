import { api } from '../api/client'

/** Le navigateur sait-il recevoir des notifications push ? (iOS : uniquement une fois la PWA installée.) */
export function pushSupporte(): boolean {
  return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
}

/** Clé VAPID base64url → octets attendus par pushManager.subscribe(). */
export function cleVersOctets(base64url: string): Uint8Array<ArrayBuffer> {
  const base64 = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4))
    .replace(/-/g, '+')
    .replace(/_/g, '/')
  const brut = atob(base64)
  const octets = new Uint8Array(new ArrayBuffer(brut.length))
  for (let i = 0; i < brut.length; i++) octets[i] = brut.charCodeAt(i)
  return octets
}

async function enregistrement(): Promise<ServiceWorkerRegistration> {
  const existant = await navigator.serviceWorker.getRegistration()
  if (!existant) {
    throw new Error(
      "Notifications indisponibles en développement : utilisez la version construite (npm run build && npm run preview) ou l'application installée.",
    )
  }
  return navigator.serviceWorker.ready
}

/** Demande l'autorisation, abonne cet appareil et transmet l'abonnement à l'API. */
export async function activerPush(clePublique: string): Promise<void> {
  const permission = await Notification.requestPermission()
  if (permission !== 'granted') {
    throw new Error('Notifications refusées : autorisez-les dans les réglages du navigateur pour ce site.')
  }
  const reg = await enregistrement()
  const abonnement =
    (await reg.pushManager.getSubscription()) ??
    (await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: cleVersOctets(clePublique),
    }))
  await api('/push/abonnements', 'POST', abonnement.toJSON())
}

export async function desactiverPush(): Promise<void> {
  const reg = await navigator.serviceWorker.getRegistration()
  const abonnement = await reg?.pushManager.getSubscription()
  if (!abonnement) return
  await api('/push/desabonnement', 'POST', { endpoint: abonnement.endpoint })
  await abonnement.unsubscribe()
}

export async function appareilAbonne(): Promise<boolean> {
  if (!pushSupporte()) return false
  const reg = await navigator.serviceWorker.getRegistration()
  return Boolean(await reg?.pushManager.getSubscription())
}
