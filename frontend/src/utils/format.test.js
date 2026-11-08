import { describe, expect, it } from 'vitest'
import { qty, rp, uangCepat } from './format'

describe('uangCepat', () => {
  it('menawarkan pembulatan ke atas ke pecahan umum, terurut tanpa duplikat', () => {
    expect(uangCepat(18500)).toEqual([20000, 50000, 100000])
    expect(uangCepat(70000)).toEqual([80000, 100000]) // 70.000 sudah kelipatan 5.000 dan 10.000
    expect(uangCepat(1000)).toEqual([5000, 10000, 20000, 50000])
  })

  it('tidak menawarkan nilai yang sama dengan total (itu tombol "Uang pas")', () => {
    expect(uangCepat(100000)).toEqual([])
    expect(uangCepat(50000)).toEqual([60000, 100000])
  })
})

describe('format angka', () => {
  it('rupiah dibulatkan dan memakai titik ribuan', () => {
    expect(rp(185000)).toBe('Rp185.000')
    expect(rp(null)).toBe('Rp0')
  })

  it('qty memakai koma desimal tanpa nol berlebih', () => {
    expect(qty(19.2)).toBe('19,2')
    expect(qty(8)).toBe('8')
  })
})
