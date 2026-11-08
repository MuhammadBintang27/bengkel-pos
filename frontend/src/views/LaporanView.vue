<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { errMsg, get } from '@/api/http'
import PageHeader from '@/components/PageHeader.vue'
import StatCard from '@/components/StatCard.vue'
import { qty, rp, tgl, ymd } from '@/utils/format'

const d = ref(null)
const error = ref('')
const memuat = ref(false)

const hariIni = () => new Date()
const mundur = (n) => new Date(Date.now() - n * 864e5)
const PRESET = [
  { v: 'hari', l: 'Hari ini', r: () => [hariIni(), hariIni()] },
  { v: '7', l: '7 hari', r: () => [mundur(6), hariIni()] },
  { v: 'bulan', l: 'Bulan ini', r: () => [new Date(hariIni().getFullYear(), hariIni().getMonth(), 1), hariIni()] },
  { v: 'lalu', l: 'Bulan lalu', r: () => [new Date(hariIni().getFullYear(), hariIni().getMonth() - 1, 1), new Date(hariIni().getFullYear(), hariIni().getMonth(), 0)] },
]
const preset = ref('bulan')
const rentang = ref(PRESET[2].r())

watch(preset, (v) => {
  const p = PRESET.find((x) => x.v === v)
  if (p) rentang.value = p.r()
})
// Memilih tanggal sendiri: pilihan cepat dilepas. Memuat hanya bila kedua ujung rentang sudah terisi.
watch(rentang, (v) => {
  if (v?.[0] && v?.[1]) load()
})

async function load() {
  const [a, b] = rentang.value
  memuat.value = true
  error.value = ''
  try {
    d.value = (await get('/laporan', { dari: ymd(a), sampai: ymd(b) })).data
  } catch (e) {
    error.value = e.response?.data?.errors ? Object.values(e.response.data.errors)[0][0] : errMsg(e)
  } finally {
    memuat.value = false
  }
}
onMounted(load)

const maks = computed(() => Math.max(1, ...(d.value?.grafik.data.map((x) => x.omzet) ?? [0])))
const labelBar = (k) => (d.value.grafik.per === 'hari' ? tgl(k) : new Date(`${k}-01`).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }))
const maksKomisi = computed(() => Math.max(1, ...(d.value?.komisi_mekanik.map((x) => x.komisi) ?? [0])))
const periode = computed(() => (d.value ? (d.value.dari === d.value.sampai ? tgl(d.value.dari) : `${tgl(d.value.dari)} - ${tgl(d.value.sampai)}`) : ''))
</script>

<template>
  <PageHeader title="Laporan" :subtitle="d ? `Periode ${periode}` : 'Omzet, laba, komisi, dan barang terlaris.'" />

  <div class="panel filter">
    <SelectButton v-model="preset" :options="PRESET" option-label="l" option-value="v" :allow-empty="false" aria-label="Periode cepat" />
    <DatePicker v-model="rentang" selection-mode="range" date-format="dd/mm/yy" show-icon :manual-input="false" :max-date="new Date()" placeholder="Pilih tanggal sendiri" class="tgl" aria-label="Rentang tanggal" @update:model-value="preset = ''" />
  </div>

  <Message v-if="error" severity="error" :closable="false" class="mb">{{ error }}</Message>
  <div v-if="!d && !error" class="stats"><Skeleton v-for="i in 4" :key="i" height="96px" border-radius="12px" /></div>

  <div v-if="d" class="isi" :class="{ pudar: memuat }">
    <section class="stats">
      <StatCard icon="kas" label="Omzet" :value="rp(d.ringkas.omzet)" :hint="`${d.ringkas.transaksi} transaksi lunas`" tone="green" />
      <StatCard icon="laporan" label="Laba kotor" :value="rp(d.ringkas.laba_kotor)" hint="Omzet dikurangi modal barang dan komisi" :tone="d.ringkas.laba_kotor < 0 ? 'red' : 'primary'" />
      <StatCard icon="mekanik" label="Komisi mekanik" :value="rp(d.ringkas.komisi)" hint="Bagian mekanik dari jasa" tone="orange" />
      <StatCard icon="stok" label="Modal barang (HPP)" :value="rp(d.ringkas.hpp)" hint="Harga beli sparepart yang terjual" />
    </section>

    <section class="panel kotak">
      <h3>Dari mana omzet berasal</h3>
      <div class="pecah">
        <div><small class="muted">Jasa servis</small><strong class="num">{{ rp(d.ringkas.jasa) }}</strong></div>
        <div><small class="muted">Penjualan sparepart</small><strong class="num">{{ rp(d.ringkas.sparepart) }}</strong></div>
        <div><small class="muted">Diskon yang diberikan</small><strong class="num">{{ rp(d.ringkas.diskon) }}</strong></div>
      </div>
    </section>

    <section class="panel kotak">
      <h3>Omzet per {{ d.grafik.per }}</h3>
      <ul v-if="d.grafik.data.length" class="bars">
        <li v-for="x in d.grafik.data" :key="x.kunci">
          <span class="lbl">{{ labelBar(x.kunci) }}</span>
          <span class="bar"><i :style="{ width: `${(x.omzet / maks) * 100}%` }" /></span>
          <strong class="num">{{ rp(x.omzet) }}</strong>
        </li>
      </ul>
      <p v-else class="kosong muted">Belum ada transaksi lunas pada periode ini.</p>
    </section>

    <section class="dua">
      <div class="panel kotak">
        <h3>Komisi per mekanik</h3>
        <ul v-if="d.komisi_mekanik.length" class="bars">
          <li v-for="m in d.komisi_mekanik" :key="m.nama" class="rapat">
            <span class="lbl">{{ m.nama }}<small class="muted">{{ m.jasa }} jasa</small></span>
            <span class="bar"><i :style="{ width: `${(m.komisi / maksKomisi) * 100}%` }" /></span>
            <strong class="num">{{ rp(m.komisi) }}</strong>
          </li>
        </ul>
        <p v-else class="kosong muted">Belum ada komisi pada periode ini.</p>
      </div>

      <div class="panel kotak">
        <h3>Sparepart terlaris</h3>
        <ol v-if="d.sparepart_terlaris.length" class="peringkat">
          <li v-for="s in d.sparepart_terlaris" :key="s.nama">
            <span class="teks">{{ s.nama }}<small class="muted">Terjual {{ qty(s.qty) }} {{ s.satuan }}</small></span>
            <strong class="num">{{ rp(s.nilai) }}</strong>
          </li>
        </ol>
        <p v-else class="kosong muted">Belum ada sparepart terjual pada periode ini.</p>
      </div>
    </section>

    <section class="panel kotak">
      <h3>Pembelian dari supplier</h3>
      <ul v-if="d.pembelian.length" class="peringkat polos">
        <li v-for="p in d.pembelian" :key="p.nama">
          <span class="teks">{{ p.nama }}<small class="muted">{{ p.faktur }} penerimaan</small></span>
          <strong class="num">{{ rp(p.total) }}</strong>
        </li>
      </ul>
      <p v-else class="kosong muted">Tidak ada penerimaan barang pada periode ini.</p>
    </section>
  </div>
</template>

<style scoped>
.filter {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 16px;
}
.tgl {
  min-width: 16rem;
}
.mb {
  margin-bottom: 16px;
}
.isi {
  display: grid;
  gap: 16px;
  transition: opacity 0.15s;
}
.pudar {
  opacity: 0.55;
}
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 16px;
}
.dua {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 16px;
}
.kotak {
  padding: 20px;
}
.kotak h3 {
  margin: 0 0 14px;
  font-size: 1rem;
}
.pecah {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
}
.pecah div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.pecah strong {
  font-size: 1.2rem;
}
.bars,
.peringkat {
  display: grid;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.bars li {
  display: grid;
  grid-template-columns: 8.5rem 1fr auto;
  align-items: center;
  gap: 12px;
}
.lbl small,
.teks small {
  display: block;
}
.bar {
  height: 10px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
  overflow: hidden;
}
.bar i {
  display: block;
  height: 100%;
  min-width: 2px;
  border-radius: 999px;
  background: var(--p-primary-color);
}
.peringkat {
  counter-reset: r;
}
.peringkat li {
  display: flex;
  align-items: center;
  gap: 12px;
  counter-increment: r;
}
.peringkat li::before {
  content: counter(r);
  width: 26px;
  height: 26px;
  flex: none;
  display: grid;
  place-items: center;
  border-radius: 50%;
  font-size: 0.8rem;
  font-weight: 700;
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
  color: var(--p-primary-color);
}
.peringkat.polos li::before {
  display: none;
}
.teks {
  flex: 1;
  min-width: 0;
}
.kosong {
  margin: 0;
  padding: 16px 0;
  text-align: center;
}
@media (max-width: 600px) {
  .bars li {
    grid-template-columns: 1fr auto;
  }
  .bars .bar {
    grid-column: 1 / -1;
    order: 3;
  }
}
</style>
