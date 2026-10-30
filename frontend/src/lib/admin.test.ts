import { describe, expect, it } from 'vitest'
import { depuis, enRetard } from './admin'

const maintenant = new Date('2026-10-30T12:00:00+01:00')

describe('depuis', () => {
  it('formule relative en français', () => {
    expect(depuis(null, maintenant)).toBe('jamais')
    expect(depuis('2026-10-30T11:59:40+01:00', maintenant)).toBe("à l'instant")
    expect(depuis('2026-10-30T09:00:00+01:00', maintenant)).toBe('il y a 3 heures')
    expect(depuis('2026-10-29T10:00:00+01:00', maintenant)).toBe('hier')
  })
})

describe('enRetard', () => {
  it('signale une collecte de plus de 7 h sur une source active', () => {
    expect(enRetard('2026-10-30T06:15:00+01:00', true, maintenant)).toBe(false)
    expect(enRetard('2026-10-30T04:00:00+01:00', true, maintenant)).toBe(true)
    expect(enRetard('2026-10-30T04:00:00+01:00', false, maintenant)).toBe(false)
    expect(enRetard(null, true, maintenant)).toBe(false)
  })
})
