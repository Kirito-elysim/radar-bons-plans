// Génère les icônes PNG de la PWA (sans dépendance) : un radar crème sur fond marine, avec un point « signal ».
import { writeFileSync } from 'node:fs'
import { deflateSync } from 'node:zlib'

const MARINE = [20, 33, 61]
const CREME = [246, 243, 236]
const SIGNAL = [230, 57, 70]

function crc32(buf) {
  let c = ~0
  for (const b of buf) {
    c ^= b
    for (let k = 0; k < 8; k++) c = (c >>> 1) ^ (0xedb88320 & -(c & 1))
  }
  return ~c >>> 0
}

function chunk(type, data) {
  const len = Buffer.alloc(4)
  len.writeUInt32BE(data.length)
  const corps = Buffer.concat([Buffer.from(type), data])
  const crc = Buffer.alloc(4)
  crc.writeUInt32BE(crc32(corps))
  return Buffer.concat([len, corps, crc])
}

function png(taille, pixel) {
  const lignes = []
  for (let y = 0; y < taille; y++) {
    const ligne = Buffer.alloc(1 + taille * 4)
    for (let x = 0; x < taille; x++) ligne.set(pixel(x, y), 1 + x * 4)
    lignes.push(ligne)
  }
  const ihdr = Buffer.alloc(13)
  ihdr.writeUInt32BE(taille, 0)
  ihdr.writeUInt32BE(taille, 4)
  ihdr.set([8, 6, 0, 0, 0], 8)
  return Buffer.concat([
    Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
    chunk('IHDR', ihdr),
    chunk('IDAT', deflateSync(Buffer.concat(lignes), { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ])
}

/** Couleur d'un point (u, v) dans [0, 1], ou null si transparent. */
function motif(u, v, { arrondi, echelle }) {
  // Fond : carré arrondi (arrondi = 0 → carré plein pour l'icône « maskable »)
  const r = arrondi
  const dx = Math.max(r - u, 0, u - (1 - r))
  const dy = Math.max(r - v, 0, v - (1 - r))
  if (r > 0 && dx * dx + dy * dy > r * r) return null

  const cx = 0.5
  const cy = 0.6
  const x = (u - cx) / echelle
  const y = (v - cy) / echelle
  const d = Math.hypot(x, y)
  const angle = Math.atan2(-y, x)

  // Point « signal » en haut à droite
  if (Math.hypot(x - 0.3, y + 0.3) < 0.065) return SIGNAL
  // Centre
  if (d < 0.045) return CREME
  // Trois arcs concentriques ouverts vers le haut (de -20° à 200°, symétriques)
  const dansSecteur = angle > -0.35 || angle < -Math.PI + 0.35
  for (const rayon of [0.13, 0.23, 0.33]) {
    if (dansSecteur && Math.abs(d - rayon) < 0.032) return CREME
  }
  return MARINE
}

function icone(taille, options) {
  const ech = 4 // sur-échantillonnage pour l'anticrénelage
  return png(taille, (px, py) => {
    let rr = 0,
      gg = 0,
      bb = 0,
      aa = 0
    for (let i = 0; i < ech; i++)
      for (let j = 0; j < ech; j++) {
        const c = motif((px + (i + 0.5) / ech) / taille, (py + (j + 0.5) / ech) / taille, options)
        if (c) {
          rr += c[0]
          gg += c[1]
          bb += c[2]
          aa += 255
        }
      }
    const n = ech * ech
    const a = aa / n
    return a === 0
      ? [0, 0, 0, 0]
      : [
          Math.round(((rr / n) * 255) / a),
          Math.round(((gg / n) * 255) / a),
          Math.round(((bb / n) * 255) / a),
          Math.round(a),
        ]
  })
}

const dossier = new URL('../public/icones/', import.meta.url)
writeFileSync(new URL('icone-192.png', dossier), icone(192, { arrondi: 0.22, echelle: 1 }))
writeFileSync(new URL('icone-512.png', dossier), icone(512, { arrondi: 0.22, echelle: 1 }))
// « maskable » : fond plein et motif réduit dans la zone de sécurité (80 %)
writeFileSync(new URL('icone-maskable-512.png', dossier), icone(512, { arrondi: 0, echelle: 0.78 }))
writeFileSync(new URL('apple-touch-icon.png', dossier), icone(180, { arrondi: 0, echelle: 0.9 }))
console.log('Icônes générées dans public/icones/')
