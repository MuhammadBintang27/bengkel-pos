<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { errMsg, get } from '@/api/http'
import TunasLogo from '@/components/TunasLogo.vue'
import { useAuthStore } from '@/stores/auth'
import { METODE, qty, rp, tglJam } from '@/utils/format'

// Halaman cetak: tanpa menu aplikasi. Ukuran kertas dari Pengaturan (58 mm, 80 mm, atau A5).
const route = useRoute()
const auth = useAuthStore()
const t = ref(null)
const error = ref('')
const ukuran = ref(auth.pengaturan.ukuran_nota || '58')

const PAGE = {
  58: '@page { size: 58mm auto; margin: 2mm }',
  80: '@page { size: 80mm auto; margin: 3mm }',
  A5: '@page { size: A5; margin: 12mm }',
}
let styleEl

const p = computed(() => auth.pengaturan)
const lunas = computed(() => t.value?.status === 'lunas')
const pemilik = computed(() => t.value?.pelanggan || t.value?.kendaraan?.pelanggan)
const waktu = computed(() => tglJam(t.value?.lunas_at || t.value?.created_at))

onMounted(async () => {
  styleEl = document.createElement('style')
  styleEl.textContent = PAGE[ukuran.value] || PAGE[58]
  document.head.appendChild(styleEl)
  document.title = 'Nota'
  try {
    t.value = (await get(`/transaksi/${route.params.id}`)).data
    document.title = `Nota ${t.value.nomor}`
    // ?print=1 dari tombol Cetak Nota: langsung buka dialog cetak.
    if (route.query.print) setTimeout(() => window.print(), 400)
  } catch (e) {
    error.value = e.response?.status === 404 ? 'Transaksi tidak ditemukan.' : errMsg(e)
  }
})
onBeforeUnmount(() => styleEl?.remove())

const cetakNota = () => window.print()
const tutup = () => window.close()

function gantiUkuran(u) {
  ukuran.value = u
  styleEl.textContent = PAGE[u]
}
</script>

<template>
  <div class="screen">
    <div class="bar no-print">
      <div class="ukuran" role="group" aria-label="Ukuran kertas">
        <button v-for="u in ['58', '80', 'A5']" :key="u" type="button" :class="{ on: ukuran === u }" @click="gantiUkuran(u)">{{ u === 'A5' ? 'A5' : `${u} mm` }}</button>
      </div>
      <button type="button" class="cetak" :disabled="!t" @click="cetakNota"><i class="pi pi-print" /> Cetak</button>
      <button type="button" class="tutup" @click="tutup">Tutup</button>
    </div>

    <p v-if="error" class="galat">{{ error }}</p>

    <article v-else-if="t" class="paper" :class="`u${ukuran}`">
      <header class="kop">
        <img v-if="p.logo" :src="p.logo" alt="" class="logo" />
        <TunasLogo v-else :size="34" class="logo-svg" />
        <strong class="nama">{{ p.nama_bengkel }}</strong>
        <span v-if="p.alamat">{{ p.alamat }}</span>
        <span v-if="p.telepon">Telp {{ p.telepon }}</span>
      </header>

      <div v-if="t.status === 'batal'" class="cap batal">DIBATALKAN</div>
      <div v-else-if="!lunas" class="cap">BELUM LUNAS</div>

      <hr />
      <dl class="meta">
        <div><dt>No</dt><dd>{{ t.nomor }}</dd></div>
        <div><dt>Tanggal</dt><dd>{{ waktu }}</dd></div>
        <div><dt>Kasir</dt><dd>{{ t.user?.nama }}</dd></div>
        <template v-if="t.kendaraan">
          <div><dt>Motor</dt><dd>{{ [t.kendaraan.plat, [t.kendaraan.merk, t.kendaraan.tipe].filter(Boolean).join(' ')].filter(Boolean).join(' · ') }}</dd></div>
          <div v-if="t.km != null"><dt>KM</dt><dd>{{ qty(t.km) }}</dd></div>
        </template>
        <div v-if="pemilik"><dt>Pelanggan</dt><dd>{{ pemilik.nama }}</dd></div>
      </dl>
      <hr />

      <section v-if="t.jasas.length">
        <h4>Jasa</h4>
        <div v-for="j in t.jasas" :key="j.id" class="item">
          <div class="rw"><span class="nm">{{ j.nama }}</span><span class="num">{{ rp(j.harga) }}</span></div>
          <div v-if="j.diskon" class="rw dis"><span>Diskon</span><span class="num">-{{ rp(j.diskon) }}</span></div>
        </div>
      </section>

      <section v-if="t.spareparts.length">
        <h4>Sparepart</h4>
        <div v-for="s in t.spareparts" :key="s.id" class="item">
          <div class="nm">{{ s.nama }}</div>
          <div class="rw"><span>{{ qty(s.qty) }} {{ s.satuan }} x {{ rp(s.harga) }}</span><span class="num">{{ rp(Math.round(s.qty * s.harga)) }}</span></div>
          <div v-if="s.diskon" class="rw dis"><span>Diskon</span><span class="num">-{{ rp(s.diskon) }}</span></div>
        </div>
      </section>

      <hr />
      <div class="rw total"><span>TOTAL</span><span class="num">{{ rp(t.total) }}</span></div>
      <template v-if="lunas">
        <div class="rw"><span>Bayar ({{ METODE[t.metode_bayar].label }})</span><span class="num">{{ rp(t.dibayar) }}</span></div>
        <div v-if="t.metode_bayar === 'tunai'" class="rw"><span>Kembali</span><span class="num">{{ rp(t.kembalian) }}</span></div>
      </template>

      <hr />
      <footer class="kaki">{{ p.footer_nota }}</footer>
    </article>
  </div>
</template>

<style scoped>
/* Kertas selalu putih-hitam, tidak ikut tema/mode gelap. */
.screen {
  min-height: 100dvh;
  padding: 16px;
  background: #e5e7eb;
  color-scheme: light;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}
.bar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: center;
}
.ukuran {
  display: flex;
  background: #fff;
  border-radius: 8px;
  padding: 3px;
  gap: 2px;
}
.ukuran button {
  border: 0;
  background: none;
  padding: 7px 12px;
  border-radius: 6px;
  font: inherit;
  cursor: pointer;
  color: #334155;
}
.ukuran button.on {
  background: #1e293b;
  color: #fff;
}
.cetak,
.tutup {
  border: 0;
  padding: 9px 16px;
  border-radius: 8px;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}
.cetak {
  background: #2563eb;
  color: #fff;
}
.cetak:disabled {
  opacity: 0.5;
}
.tutup {
  background: #fff;
  color: #334155;
}
.galat {
  background: #fff;
  padding: 16px 24px;
  border-radius: 8px;
  color: #b91c1c;
}

.paper {
  background: #fff;
  color: #000;
  padding: 3mm;
  box-shadow: 0 4px 24px rgb(0 0 0 / 0.18);
  font-family: 'Courier New', ui-monospace, monospace;
  font-size: 11px;
  line-height: 1.35;
}
.u58 {
  width: 58mm;
}
.u80 {
  width: 80mm;
  font-size: 12px;
}
.uA5 {
  width: 148mm;
  min-height: 210mm;
  padding: 12mm;
  font-family: Inter, -apple-system, 'Segoe UI', Roboto, sans-serif;
  font-size: 13px;
}
.kop {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 1px;
}
.logo {
  max-width: 40mm;
  max-height: 14mm;
  object-fit: contain;
  margin-bottom: 3px;
}
.logo-svg {
  margin-bottom: 3px;
  filter: grayscale(1);
}
.nama {
  font-size: 1.25em;
}
hr {
  border: 0;
  border-top: 1px dashed #000;
  margin: 6px 0;
}
.cap {
  margin: 6px 0 0;
  padding: 3px;
  border: 2px solid #000;
  text-align: center;
  font-weight: 700;
  letter-spacing: 0.1em;
}
.cap.batal {
  text-decoration: line-through;
}
h4 {
  margin: 4px 0 2px;
  font-size: 1em;
  text-transform: uppercase;
}
.meta {
  margin: 0;
}
.meta div {
  display: flex;
  gap: 6px;
}
.meta dt {
  flex: none;
  min-width: 15mm;
}
.meta dd {
  margin: 0;
  word-break: break-word;
}
.item {
  margin-bottom: 4px;
}
.rw {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}
.rw > span:first-child {
  min-width: 0;
}
.nm {
  font-weight: 700;
  word-break: break-word;
}
.rw .nm {
  font-weight: 400;
}
.dis {
  padding-left: 8px;
}
.num {
  white-space: nowrap;
  text-align: right;
}
.total {
  font-size: 1.2em;
  font-weight: 700;
}
.kaki {
  text-align: center;
  white-space: pre-line;
}

@media print {
  .screen {
    padding: 0;
    background: #fff;
    display: block;
  }
  .no-print {
    display: none !important;
  }
  .paper {
    box-shadow: none;
    margin: 0 auto;
  }
  .u58,
  .u80 {
    width: 100%;
    padding: 0;
  }
  .uA5 {
    width: 100%;
    min-height: 0;
    padding: 0;
  }
}
</style>
