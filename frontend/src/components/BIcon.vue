<script setup>
// Ikon garis bertema bengkel (24×24, warna ikut currentColor). Pakai: <BIcon name="motor" />
const props = defineProps({ name: { type: String, required: true }, size: { type: [Number, String], default: 20 } })

const GEAR =
  'M9.75 5.16L10.33 2.65L13.67 2.65L14.25 5.16L15.24 5.57L17.43 4.21L19.79 6.57L18.43 8.76L18.84 9.75L21.35 10.33L21.35 13.67L18.84 14.25L18.43 15.24L19.79 17.43L17.43 19.79L15.24 18.43L14.25 18.84L13.67 21.35L10.33 21.35L9.75 18.84L8.76 18.43L6.57 19.79L4.21 17.43L5.57 15.24L5.16 14.25L2.65 13.67L2.65 10.33L5.16 9.75L5.57 8.76L4.21 6.57L6.57 4.21L8.76 5.57Z'

// Setiap ikon: daftar elemen [tag, atribut].
const ICONS = {
  // Speedometer
  dashboard: [
    ['path', { d: 'M4.2 18a8.6 8.6 0 1 1 15.6 0' }],
    ['path', { d: 'M12 14.5l4.2-4.7' }],
    ['circle', { cx: 12, cy: 14.5, r: 1.6 }],
    ['path', { d: 'M6.3 12.2l1.3.5M12 6.4v1.4M17.7 12.2l-1.3.5M8.2 8.3l.9 1' }],
  ],
  // Motor tampak samping
  motor: [
    ['circle', { cx: 5.5, cy: 16.5, r: 3 }],
    ['circle', { cx: 18.5, cy: 16.5, r: 3 }],
    ['path', { d: 'M18.5 16.5L15.6 8H13.5' }],
    ['path', { d: 'M5.5 16.5L8.3 11.5H15' }],
    ['path', { d: 'M8.5 16.5h5.5l2.4-5' }],
    ['path', { d: 'M7 11.5l1.2-2.2h3.6' }],
  ],
  // Kunci pas + obeng
  servis: [
    ['path', { d: 'M14.6 6.4a4 4 0 0 1 5.2-1.6l-2.6 2.6.6 2.4 2.4.6 2.6-2.6a4 4 0 0 1-5.4 5L8.6 21.6a2 2 0 0 1-2.9-2.9l9.8-8.8a4 4 0 0 1-.9-3.5Z' }],
    ['path', { d: 'M2.6 4.9l2.3-2.3 3.6 3.6-2.3 2.3Z' }],
    ['path', { d: 'M7.4 7.4l5.4 5.4' }],
  ],
  // Roda gigi (sparepart)
  sparepart: [
    ['path', { d: GEAR }],
    ['circle', { cx: 12, cy: 12, r: 3.2 }],
  ],
  // Kaleng oli
  oli: [
    ['path', { d: 'M3.5 10h11.2l3.6-2.4 3.2 1.2-5.4 7.7H5.5a2 2 0 0 1-2-2Z' }],
    ['path', { d: 'M7 10V7.5h4V10M6 7.5h6' }],
    ['path', { d: 'M21.5 13.3c0 1.1-.7 2-1.5 2s-1.5-.9-1.5-2c0-.9 1.5-2.8 1.5-2.8s1.5 1.9 1.5 2.8Z' }],
  ],
  // Busi
  busi: [
    ['path', { d: 'M10 2.5h4v4h-4z' }],
    ['path', { d: 'M8.5 6.5h7l-1 6h-5Z' }],
    ['path', { d: 'M9.5 12.5h5v3h-5zM10.5 15.5v3.5h3' }],
    ['path', { d: 'M12 19v2.5M8.5 9.5h7' }],
  ],
  // Ban
  ban: [
    ['circle', { cx: 12, cy: 12, r: 9 }],
    ['circle', { cx: 12, cy: 12, r: 4.5 }],
    ['path', { d: 'M12 3v4.5M12 16.5V21M3 12h4.5M16.5 12H21M5.6 5.6l3.2 3.2M15.2 15.2l3.2 3.2M18.4 5.6l-3.2 3.2M8.8 15.2l-3.2 3.2' }],
  ],
  // Truk pengiriman (penerimaan barang)
  truk: [
    ['path', { d: 'M2.5 6.5h11v10h-11z' }],
    ['path', { d: 'M13.5 9.5h4l3.5 3.5v3.5h-7.5' }],
    ['circle', { cx: 6.5, cy: 17.5, r: 2 }],
    ['circle', { cx: 17, cy: 17.5, r: 2 }],
    ['path', { d: 'M5.5 10l2 2 3-3.5' }],
  ],
  // Tumpukan kardus (stok)
  stok: [
    ['path', { d: 'M3.5 12.5h8v8h-8zM12.5 12.5h8v8h-8zM8 4.5h8v8H8z' }],
    ['path', { d: 'M6.5 12.5v2.5M15.5 12.5v2.5M11 4.5V7' }],
  ],
  // Nota
  nota: [
    ['path', { d: 'M5.5 2.5h13v19l-2.2-1.5-2.1 1.5-2.2-1.5-2.2 1.5-2.1-1.5-2.2 1.5Z' }],
    ['path', { d: 'M8.5 7.5h7M8.5 11h7M8.5 14.5h4' }],
  ],
  // Keranjang (penjualan langsung)
  jual: [
    ['path', { d: 'M2.5 3.5h2.6l2.3 11h10.7l2.4-7.5H6.2' }],
    ['circle', { cx: 9, cy: 19, r: 1.6 }],
    ['circle', { cx: 17, cy: 19, r: 1.6 }],
  ],
  // Riwayat (jam berputar)
  riwayat: [
    ['path', { d: 'M3.5 12a8.5 8.5 0 1 0 2.5-6' }],
    ['path', { d: 'M3 3.5V7.5h4' }],
    ['path', { d: 'M12 7.5V12l3 2' }],
  ],
  // Mekanik: helm + kunci
  mekanik: [
    ['path', { d: 'M4.5 13a7.5 7.5 0 0 1 15 0v1.5h-15Z' }],
    ['path', { d: 'M12 5.5v4M8.6 6.3l1.2 3.3M15.4 6.3l-1.2 3.3' }],
    ['path', { d: 'M3 14.5h18' }],
    ['path', { d: 'M7.5 18.5a4.8 4.8 0 0 0 9 0' }],
  ],
  // Dompet (kas / saldo)
  kas: [
    ['path', { d: 'M3.5 6.5a2 2 0 0 1 2-2h11v4' }],
    ['path', { d: 'M3.5 6.5v11a2 2 0 0 0 2 2h14v-11h-14a2 2 0 0 1-2-2Z' }],
    ['circle', { cx: 16, cy: 14, r: 1.4 }],
  ],
  // Grafik naik (laporan)
  laporan: [
    ['path', { d: 'M3.5 20.5h17' }],
    ['path', { d: 'M6 16.5v-4M10.5 16.5v-7M15 16.5v-5M19.5 16.5V6' }],
    ['path', { d: 'M5 9l5-4.5 4.5 3 5-4' }],
  ],
  // Lemari laci (master data)
  master: [
    ['path', { d: 'M4 3.5h16v17H4z' }],
    ['path', { d: 'M4 9.2h16M4 14.8h16' }],
    ['path', { d: 'M10 6.3h4M10 12h4M10 17.7h4' }],
  ],
  // Panel setel (pengaturan)
  pengaturan: [
    ['path', { d: 'M4 6.5h9M17 6.5h3M4 12h3M11 12h9M4 17.5h11M19 17.5h1' }],
    ['circle', { cx: 15, cy: 6.5, r: 2 }],
    ['circle', { cx: 9, cy: 12, r: 2 }],
    ['circle', { cx: 17, cy: 17.5, r: 2 }],
  ],
  // Pelanggan
  pelanggan: [
    ['circle', { cx: 9, cy: 8, r: 3.5 }],
    ['path', { d: 'M2.5 20a6.5 6.5 0 0 1 13 0' }],
    ['path', { d: 'M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.2a6.5 6.5 0 0 1 3.5 5.8' }],
  ],
  // Tangan + koin (kasbon / pencairan)
  cair: [
    ['circle', { cx: 15.5, cy: 6.5, r: 3.5 }],
    ['path', { d: 'M15.5 5v3' }],
    ['path', { d: 'M2.5 14.5h3.5l3.3-1.5h4.2a1.5 1.5 0 0 1 0 3H10' }],
    ['path', { d: 'M13.5 16l4.6-2.2a1.6 1.6 0 0 1 1.8 2.6L14 20.5H6l-3.5-1.5' }],
  ],
}

const icon = () => ICONS[props.name] || ICONS.sparepart
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    class="bicon"
  >
    <component :is="tag" v-for="([tag, attrs], i) in icon()" :key="i" v-bind="attrs" />
  </svg>
</template>

<style scoped>
.bicon {
  flex: none;
  display: inline-block;
  vertical-align: middle;
}
</style>
