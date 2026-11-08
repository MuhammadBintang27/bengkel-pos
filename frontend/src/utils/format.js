export const rp = (n) => 'Rp' + Math.round(Number(n || 0)).toLocaleString('id-ID')

export const qty = (n) => Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 })

export const tgl = (d) => (d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-')

export const tglJam = (d) => (d ? new Date(d).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-')

export const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

export const ROLE_LABEL = { owner: 'Owner', kasir: 'Kasir', mekanik: 'Mekanik' }

export const STATUS = {
  antri: { label: 'Antri', severity: 'secondary' },
  dikerjakan: { label: 'Dikerjakan', severity: 'info' },
  selesai: { label: 'Selesai', severity: 'warn' },
  lunas: { label: 'Lunas', severity: 'success' },
  batal: { label: 'Batal', severity: 'danger' },
}

export const METODE = {
  tunai: { label: 'Tunai', icon: 'pi pi-money-bill' },
  transfer: { label: 'Transfer', icon: 'pi pi-building-columns' },
  qris: { label: 'QRIS', icon: 'pi pi-qrcode' },
}

/** Pembulatan ke atas untuk tombol cepat uang tunai: 10.000, 50.000, 100.000 terdekat di atas total. */
export function uangCepat(total) {
  const out = new Set()
  for (const k of [5000, 10000, 20000, 50000, 100000]) {
    const v = Math.ceil(total / k) * k
    if (v > total) out.add(v)
  }
  return [...out].sort((a, b) => a - b).slice(0, 4)
}

/** Jenis pergerakan stok untuk kartu stok. */
export const JENIS_STOK = {
  awal: { label: 'Stok awal', severity: 'info' },
  masuk: { label: 'Barang masuk', severity: 'success' },
  keluar: { label: 'Terjual / dipakai', severity: 'warn' },
  kembali: { label: 'Dikembalikan', severity: 'secondary' },
  batal_masuk: { label: 'Batal penerimaan', severity: 'danger' },
  penyesuaian: { label: 'Penyesuaian', severity: 'contrast' },
  koreksi_hpp: { label: 'Koreksi HPP', severity: 'contrast' },
}

export const STATUS_BELI = {
  draft: { label: 'Draft', severity: 'secondary' },
  diterima: { label: 'Diterima', severity: 'success' },
  batal: { label: 'Batal', severity: 'danger' },
}
