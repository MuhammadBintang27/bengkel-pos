import { describe, expect, it } from 'vitest'
import { buildPalette, contrastFor, isHex } from './useAppTheme'

const lum = (hex) => {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16))
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

describe('buildPalette', () => {
  it('menaruh warna asli di 500 dan menggelap bertahap dari 50 ke 950', () => {
    const p = buildPalette('#8B4513')
    expect(p[500]).toBe('#8b4513')
    const steps = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((n) => lum(p[n]))
    for (let i = 1; i < steps.length; i++) expect(steps[i]).toBeLessThan(steps[i - 1])
  })
})

describe('contrastFor', () => {
  it('memakai teks gelap di warna terang dan putih di warna gelap', () => {
    expect(contrastFor('#f59e0b')).not.toBe('#ffffff')
    expect(contrastFor('#eab308')).not.toBe('#ffffff')
    expect(contrastFor('#1e3a8a')).toBe('#ffffff')
    expect(contrastFor('#8b4513')).toBe('#ffffff')
  })
})

describe('isHex', () => {
  it('hanya menerima #RRGGBB', () => {
    expect(isHex('#8b4513')).toBe(true)
    expect(isHex('amber')).toBe(false)
    expect(isHex('#fff')).toBe(false)
  })
})

describe('teks tombol warna bawaan', () => {
  it('putih di biru dan merah, gelap di kuning tua', () => {
    const c600 = (hex) => contrastFor(buildPalette(hex)[600])
    expect(c600('#3b82f6')).toBe('#ffffff')
    expect(c600('#ef4444')).toBe('#ffffff')
    expect(c600('#f59e0b')).not.toBe('#ffffff')
  })
})
