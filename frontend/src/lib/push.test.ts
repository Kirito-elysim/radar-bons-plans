import { describe, expect, it } from 'vitest'
import { cleVersOctets } from './push'

describe('cleVersOctets', () => {
  it('décode une clé VAPID base64url (65 octets, point non compressé)', () => {
    const cle = 'BIJpguoFO3sPdRM3SJcaGfbNtYJJ35j_CCc3evf8s96LsX7WvT-vhVq5zq9sFgsXQHO62Ns0XgElJPR1IdkXRPo'
    const octets = cleVersOctets(cle)

    expect(octets).toHaveLength(65)
    expect(octets[0]).toBe(0x04)
  })

  it('gère le remplissage manquant et les caractères - et _', () => {
    expect(Array.from(cleVersOctets('-_8'))).toEqual([0xfb, 0xff])
  })
})
