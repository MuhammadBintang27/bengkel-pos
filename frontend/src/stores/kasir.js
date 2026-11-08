import { computed, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import { get, post } from '@/api/http'
import { load, save } from '@/utils/storage'

const KEY = 'kasirKeranjang'
const EPS = 0.0001

/**
 * Keranjang kasir sparepart. Seluruhnya ada di browser (tersimpan di localStorage, jadi aman dari refresh)
 * dan baru dikirim ke server SEKALI saat bayar. Server memeriksa ulang stok dan harga dalam satu transaksi,
 * jadi bila ada yang berubah, tidak ada yang tersimpan setengah-setengah.
 *
 * Baris: { key, sparepart_id, satuan_id, kode, nama, satuan, isi, harga, qty, diskon, stok (satuan dasar), lokasi }
 */
export const useKasirStore = defineStore('kasir', () => {
  const awal = load(KEY, {})
  const baris = ref(Array.isArray(awal.baris) ? awal.baris : [])
  const tertunda = ref(Array.isArray(awal.tertunda) ? awal.tertunda : [])
  const mengirim = ref(false)

  watch([baris, tertunda], () => save(KEY, { baris: baris.value, tertunda: tertunda.value }), { deep: true })

  const subtotal = (b) => Math.round(b.qty * b.harga) - b.diskon
  const total = computed(() => baris.value.reduce((s, b) => s + subtotal(b), 0))
  const jumlahItem = computed(() => baris.value.length)
  const ids = computed(() => [...new Set(baris.value.map((b) => b.sparepart_id))])
  const totalTunda = (t) => t.baris.reduce((s, b) => s + subtotal(b), 0)

  /** Stok satuan dasar yang sudah dipakai baris keranjang untuk satu barang (kecuali satu baris). */
  const terpakai = (sparepartId, kecualiKey = null) =>
    baris.value.filter((b) => b.sparepart_id === sparepartId && b.key !== kecualiKey).reduce((s, b) => s + b.qty * b.isi, 0)

  /** Stok yang masih bisa dijual: stok server dikurangi isi keranjang. */
  const sisaDasar = (sparepartId, stokServer) => Math.round((stokServer - terpakai(sparepartId)) * 100) / 100

  const fmt = (n) => Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 })

  /** Tambah barang. Mengembalikan { ok, pesan }; stok dicek terhadap isi keranjang. */
  function tambah(sp, satuan, qty = 1) {
    const key = `${sp.id}:${satuan.satuan_id}`
    const ada = baris.value.find((b) => b.key === key)
    const butuh = ((ada?.qty ?? 0) + qty) * satuan.isi
    const sisa = sp.stok - terpakai(sp.id, key)
    if (butuh > sisa + EPS) {
      return { ok: false, pesan: `Stok ${sp.nama} tidak cukup (tersisa ${fmt(Math.max(sisa, 0))} ${sp.satuan_dasar}).` }
    }
    const data = { harga: satuan.harga_jual, isi: satuan.isi, satuan: satuan.nama, stok: sp.stok, lokasi: sp.lokasi ?? null, nama: sp.nama, kode: sp.kode }
    if (ada) Object.assign(ada, data, { qty: Math.round((ada.qty + qty) * 100) / 100 })
    else baris.value.push({ key, sparepart_id: sp.id, satuan_id: satuan.satuan_id, qty, diskon: 0, ...data })
    return { ok: true }
  }

  /** Ubah jumlah/diskon satu baris. */
  function ubah(b, { qty, diskon }) {
    const q = Math.round((qty ?? b.qty) * 100) / 100
    const d = diskon ?? b.diskon
    if (q <= 0) return hapus(b)
    const sisa = b.stok - terpakai(b.sparepart_id, b.key)
    if (q * b.isi > sisa + EPS) {
      return { ok: false, pesan: `Stok ${b.nama} tidak cukup (tersisa ${fmt(Math.max(sisa / b.isi, 0))} ${b.satuan}).` }
    }
    if (d < 0 || d > Math.round(q * b.harga)) return { ok: false, pesan: 'Potongan tidak boleh lebih besar dari harga.' }
    b.qty = q
    b.diskon = d
    return { ok: true }
  }

  function hapus(b) {
    baris.value = baris.value.filter((x) => x.key !== b.key)
    return { ok: true }
  }

  const kosongkan = () => (baris.value = [])

  /** Samakan baris dengan data terbaru dari server (stok, harga, rak) yang sudah dimuat grid. */
  function catatBarang(items) {
    for (const sp of items) {
      for (const b of baris.value) {
        if (b.sparepart_id !== sp.id) continue
        b.stok = sp.stok
        b.lokasi = sp.lokasi ?? null
        const s = sp.satuans.find((x) => x.satuan_id === b.satuan_id)
        if (s) Object.assign(b, { harga: s.harga_jual, isi: s.isi, satuan: s.nama })
      }
    }
  }

  /** Ambil stok dan harga terbaru khusus barang di keranjang (mis. setelah bayar ditolak). */
  async function segarkan() {
    if (!ids.value.length) return []
    const items = (await get('/cari/sparepart', { ids: ids.value.join(',') })).data
    catatBarang(items)
    return items
  }

  // ---- keranjang tertunda (hanya di komputer ini)
  function tahan() {
    if (!baris.value.length) return
    tertunda.value.push({ id: Date.now(), baris: baris.value })
    baris.value = []
  }

  function buka(id) {
    const t = tertunda.value.find((x) => x.id === id)
    if (!t) return
    tertunda.value = tertunda.value.filter((x) => x.id !== id)
    tahan()
    baris.value = t.baris
  }

  const buang = (id) => (tertunda.value = tertunda.value.filter((x) => x.id !== id))

  /** Bayar: kirim seluruh keranjang sekali. Berhasil → keranjang dikosongkan. Gagal → keranjang utuh, error dilempar. */
  async function kirim(metode, dibayar) {
    mengirim.value = true
    try {
      const res = await post('/penjualan', {
        items: baris.value.map((b) => ({ sparepart_id: b.sparepart_id, satuan_id: b.satuan_id, qty: b.qty, diskon: b.diskon })),
        metode_bayar: metode,
        dibayar,
        total: total.value,
      })
      kosongkan()
      return res.data
    } finally {
      mengirim.value = false
    }
  }

  return { baris, tertunda, mengirim, total, jumlahItem, ids, subtotal, totalTunda, sisaDasar, tambah, ubah, hapus, kosongkan, catatBarang, segarkan, tahan, buka, buang, kirim }
})
