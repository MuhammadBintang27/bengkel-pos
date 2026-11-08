import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useKasirStore } from './kasir'

// Busi: stok 24 pcs, bisa dijual per pcs (Rp20.000) atau per box isi 10 (Rp185.000).
const busi = { id: 1, kode: 'BSI', nama: 'Busi', stok: 24, stok_min: 5, satuan_dasar: 'pcs', lokasi: 'B1' }
const pcs = { satuan_id: 1, nama: 'pcs', isi: 1, harga_jual: 20000 }
const box = { satuan_id: 2, nama: 'box', isi: 10, harga_jual: 185000 }
const oli = { id: 2, kode: 'OLI', nama: 'Oli', stok: 3, stok_min: 0, satuan_dasar: 'liter', lokasi: null }
const liter = { satuan_id: 3, nama: 'liter', isi: 1, harga_jual: 70000 }

let s
beforeEach(() => {
  setActivePinia(createPinia())
  s = useKasirStore()
})

describe('stok keranjang', () => {
  it('stok dibagi antar satuan: box dan pcs memakai persediaan yang sama', () => {
    expect(s.tambah(busi, box, 2).ok).toBe(true) // 20 pcs
    const r = s.tambah(busi, pcs, 5) // sisa hanya 4
    expect(r.ok).toBe(false)
    expect(r.pesan).toContain('tersisa 4 pcs')
    expect(s.tambah(busi, pcs, 4).ok).toBe(true)
    expect(s.sisaDasar(busi.id, busi.stok)).toBe(0)
    expect(s.tambah(busi, pcs, 1).ok).toBe(false)
  })

  it('barang dan satuan yang sama digabung jadi satu baris', () => {
    s.tambah(busi, pcs, 2)
    s.tambah(busi, pcs, 3)
    expect(s.baris).toHaveLength(1)
    expect(s.baris[0].qty).toBe(5)
  })

  it('jumlah desimal (liter) dan penolakan yang tidak mengubah keranjang', () => {
    expect(s.tambah(oli, liter, 1.5).ok).toBe(true)
    expect(s.tambah(oli, liter, 1.6).ok).toBe(false) // 3,1 > 3
    expect(s.baris[0].qty).toBe(1.5)
    expect(s.tambah(oli, liter, 1.5).ok).toBe(true)
  })

  it('ubah jumlah dicek terhadap stok dan baris lain; jumlah 0 menghapus baris', () => {
    s.tambah(busi, box, 1) // 10 pcs
    s.tambah(busi, pcs, 5) // 5 pcs → total 15
    const b = s.baris.find((x) => x.satuan === 'pcs')

    expect(s.ubah(b, { qty: 15 }).ok).toBe(false) // 10 + 15 = 25 > 24
    expect(b.qty).toBe(5)
    expect(s.ubah(b, { qty: 14 }).ok).toBe(true) // 10 + 14 = 24
    expect(s.ubah(b, { qty: 0 }).ok).toBe(true)
    expect(s.baris).toHaveLength(1)
  })
})

describe('total dan potongan', () => {
  it('subtotal = jumlah × harga − potongan, total menjumlahkan semua baris', () => {
    s.tambah(busi, pcs, 2)
    s.tambah(busi, box, 1)
    s.ubah(s.baris[1], { diskon: 5000 })
    expect(s.total).toBe(2 * 20000 + (185000 - 5000))
  })

  it('potongan tidak boleh melebihi harga baris', () => {
    s.tambah(busi, pcs, 1)
    expect(s.ubah(s.baris[0], { diskon: 20001 }).ok).toBe(false)
    expect(s.baris[0].diskon).toBe(0)
    expect(s.ubah(s.baris[0], { diskon: 20000 }).ok).toBe(true)
    expect(s.total).toBe(0)
  })
})

describe('keranjang tertunda dan sinkron data server', () => {
  it('tahan lalu buka kembali menukar keranjang tanpa kehilangan isi', () => {
    s.tambah(busi, pcs, 2)
    s.tahan()
    expect(s.baris).toHaveLength(0)
    expect(s.tertunda).toHaveLength(1)

    s.tambah(oli, liter, 1)
    s.buka(s.tertunda[0].id)

    expect(s.baris.map((b) => b.nama)).toEqual(['Busi'])
    expect(s.tertunda).toHaveLength(1) // keranjang oli yang tadi ditahan
    expect(s.totalTunda(s.tertunda[0])).toBe(70000)
  })

  it('catatBarang memperbarui harga, stok, dan rak baris yang sudah ada', () => {
    s.tambah(busi, pcs, 2)
    s.catatBarang([{ ...busi, stok: 7, lokasi: 'C9', satuans: [{ ...pcs, harga_jual: 25000 }] }])

    const b = s.baris[0]
    expect(b.harga).toBe(25000)
    expect(b.stok).toBe(7)
    expect(b.lokasi).toBe('C9')
    expect(s.total).toBe(50000)
    expect(s.ubah(b, { qty: 8 }).ok).toBe(false) // stok baru 7
  })
})
