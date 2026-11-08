<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { get } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import { useKasirStore } from '@/stores/kasir'
import { qty as fmtQty, rp } from '@/utils/format'

// Grid barang kasir: pencarian, chip kategori, scroll tak berujung, scan barcode (Enter).
const emit = defineEmits(['pilih'])
// tanpaKeranjang: dipakai di luar kasir (mis. Detail Transaksi), stok apa adanya dan keranjang kasir tidak disentuh.
const props = defineProps({ tanpaKeranjang: Boolean })
const kasir = useKasirStore()

const q = ref('')
const kategori = ref([])
const aktif = ref(0)
const items = ref([])
const halaman = ref(0)
const habisHalaman = ref(false)
const loading = ref(false)
const error = ref('')
const sentinel = ref()
const cari = ref()
let timer
let seq = 0
let observer

// Stok yang ditampilkan sudah dikurangi isi keranjang, jadi kasir tahu sisa yang benar-benar bisa dijual.
const sisa = (s) => (props.tanpaKeranjang ? Number(s.stok) : kasir.sisaDasar(s.id, s.stok))
const habis = (s) => sisa(s) <= 0
const menipis = (s) => sisa(s) > 0 && s.stok_min > 0 && sisa(s) <= s.stok_min
const dasar = (s) => s.satuans.find((x) => x.satuan_id === s.satuan_dasar_id) || s.satuans[0]
const lain = (s) => s.satuans.filter((x) => x.satuan_id !== s.satuan_dasar_id)

async function muat(reset = false) {
  if (loading.value && !reset) return
  if (reset) {
    seq++
    halaman.value = 0
    habisHalaman.value = false
    items.value = []
  }
  if (habisHalaman.value) return
  const mine = seq
  loading.value = true
  error.value = ''
  try {
    const r = await get('/cari/sparepart', { page: halaman.value + 1, per_page: 48, q: q.value || undefined, kategori_id: aktif.value || undefined })
    if (mine !== seq) return
    items.value.push(...r.data)
    if (!props.tanpaKeranjang) kasir.catatBarang(r.data)
    halaman.value = r.current_page
    habisHalaman.value = r.current_page >= r.last_page
  } catch {
    if (mine === seq) error.value = 'Barang tidak bisa dimuat.'
  } finally {
    if (mine === seq) loading.value = false
  }
}

const cariTunda = () => {
  clearTimeout(timer)
  timer = setTimeout(() => muat(true), 280)
}
watch(aktif, () => muat(true))

/** Scan barcode / Enter: hasil tunggal langsung dipilih dengan satuan sesuai barcode. */
async function enter() {
  const teks = q.value.trim()
  if (!teks) return
  clearTimeout(timer)
  try {
    const hasil = (await get('/cari/sparepart', { q: teks })).data
    if (hasil.length !== 1) return muat(true)
    const sp = hasil[0]
    emit('pilih', sp, sp.satuans.find((s) => s.barcode === teks) || null)
    q.value = ''
    muat(true)
  } catch {
    /* biarkan hasil pencarian apa adanya */
  }
}

/** Segarkan stok barang tertentu (dipanggil saat keranjang berubah) tanpa memuat ulang seluruh grid. */
async function segarkan(ids) {
  const target = ids.filter((id) => items.value.some((s) => s.id === id))
  if (!target.length) return
  try {
    const baru = (await get('/cari/sparepart', { ids: target.join(',') })).data
    for (const b of baru) {
      const i = items.value.findIndex((s) => s.id === b.id)
      if (i >= 0) items.value[i] = b
    }
    if (!props.tanpaKeranjang) kasir.catatBarang(baru)
  } catch {
    /* stok akan benar lagi saat grid dimuat ulang */
  }
}

onMounted(async () => {
  muat(true)
  try {
    kategori.value = (await get('/cari/kategori')).data
  } catch {
    /* tanpa chip kategori, pencarian tetap jalan */
  }
  observer = new IntersectionObserver((e) => e[0].isIntersecting && muat(), { rootMargin: '200px' })
  observer.observe(sentinel.value)
})
onBeforeUnmount(() => {
  clearTimeout(timer)
  observer?.disconnect()
})

defineExpose({ fokus: () => cari.value?.$el?.focus(), segarkan, muat })
</script>

<template>
  <section class="panel grid-panel">
    <div class="tools">
      <IconField class="grow">
        <InputIcon class="pi pi-search" />
        <InputText ref="cari" v-model="q" placeholder="Cari barang, atau scan barcode lalu Enter" fluid autofocus aria-label="Cari barang" @input="cariTunda" @keydown.enter.prevent="enter" />
      </IconField>
      <Button icon="pi pi-refresh" variant="outlined" severity="secondary" aria-label="Muat ulang" @click="muat(true)" />
    </div>

    <div class="chips" role="tablist" aria-label="Kategori">
      <button class="chip-btn" :class="{ on: !aktif }" role="tab" :aria-selected="!aktif" @click="aktif = 0">Semua</button>
      <button v-for="k in kategori" :key="k.id" class="chip-btn" :class="{ on: aktif === k.id }" role="tab" :aria-selected="aktif === k.id" @click="aktif = k.id">{{ k.nama }}</button>
    </div>

    <div class="cards">
      <button v-for="s in items" :key="s.id" class="card" :disabled="habis(s)" :aria-label="`${s.nama}, ${rp(dasar(s)?.harga_jual)}`" @click="emit('pilih', s, null)">
        <strong class="nama">{{ s.nama }}</strong>
        <small v-if="s.merk" class="muted">{{ s.merk }}</small>
        <span class="harga">{{ rp(dasar(s)?.harga_jual) }} <small class="muted">/ {{ s.satuan_dasar }}</small></span>
        <span v-if="s.lokasi" class="rak" :title="`Diambil dari rak ${s.lokasi}`"><i class="pi pi-map-marker" /> Rak {{ s.lokasi }}</span>
        <span class="meta">
          <small :class="habis(s) ? 'merah' : menipis(s) ? 'oranye' : 'muted'">Stok {{ fmtQty(sisa(s)) }}</small>
          <small v-if="habis(s)" class="tanda habis-t">Habis</small>
          <small v-else-if="menipis(s)" class="tanda tipis-t">Menipis</small>
          <small v-for="x in lain(s)" :key="x.satuan_id" class="unit">{{ x.nama }}</small>
        </span>
      </button>

      <template v-if="loading && !items.length">
        <Skeleton v-for="i in 12" :key="i" height="108px" border-radius="12px" />
      </template>
      <div v-if="!loading && !items.length && !error" class="empty">
        <BIcon name="sparepart" :size="40" />
        <span>{{ q ? 'Barang tidak ditemukan' : 'Belum ada barang' }}</span>
      </div>
      <div v-if="error" class="empty">
        <span>{{ error }}</span>
        <Button label="Coba lagi" size="small" outlined @click="muat(true)" />
      </div>
      <div ref="sentinel" class="sentinel" aria-hidden="true">
        <ProgressSpinner v-if="loading && items.length" style="width: 24px; height: 24px" stroke-width="6" />
      </div>
    </div>
  </section>
</template>

<style scoped>
.grid-panel {
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 0;
  overflow: hidden;
}
.tools {
  flex: none;
  display: flex;
  gap: 8px;
}
.grow {
  flex: 1;
}
.chips {
  flex: none;
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 6px;
  scrollbar-width: thin;
}
.chip-btn {
  flex: none;
  white-space: nowrap;
  padding: 7px 14px;
  border-radius: 999px;
  border: 1px solid var(--p-content-border-color);
  background: var(--p-content-background);
  color: var(--p-text-color);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}
.chip-btn.on {
  background: var(--p-primary-color);
  border-color: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 10px;
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  align-content: start;
  /* Tinggi baris = tinggi isi kartu. Tanpa ini, saat kartu banyak baris menyusut dan nama barang (overflow tersembunyi) jadi 0. */
  grid-auto-rows: max-content;
  padding: 2px;
}
.card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-height: 108px;
  padding: 12px;
  text-align: left;
  border: 1px solid var(--p-content-border-color);
  border-radius: 12px;
  background: var(--p-content-background);
  color: var(--p-text-color);
  font: inherit;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.08s;
}
.card:hover:not(:disabled) {
  border-color: var(--p-primary-color);
  box-shadow: 0 2px 10px color-mix(in srgb, var(--p-primary-color) 22%, transparent);
}
.card:active:not(:disabled) {
  transform: scale(0.98);
}
.card:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}
.nama {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.3;
  padding-right: 4px;
}
.harga {
  color: var(--accent-text);
  font-weight: 700;
  margin-top: 2px;
}
.meta {
  margin-top: auto;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}
.unit {
  padding: 0 7px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--p-primary-color) 14%, transparent);
  color: var(--accent-text);
  font-weight: 600;
}
.rak {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  gap: 4px;
  margin-top: 2px;
  padding: 1px 8px;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  background: color-mix(in srgb, var(--p-text-color) 8%, transparent);
}
.rak .pi {
  font-size: 0.72rem;
  color: var(--accent-text);
}
.merah {
  color: var(--p-red-500);
  font-weight: 600;
}
.oranye {
  color: var(--p-orange-600);
  font-weight: 600;
}
.tanda {
  padding: 0 7px;
  border-radius: 999px;
  font-weight: 700;
  color: #fff;
}
.habis-t {
  background: var(--p-red-500);
}
.tipis-t {
  background: var(--p-orange-500);
}
.empty {
  grid-column: 1 / -1;
  display: grid;
  justify-items: center;
  gap: 8px;
  padding: 40px 0;
  color: var(--p-text-muted-color);
}
.sentinel {
  grid-column: 1 / -1;
  min-height: 8px;
  display: grid;
  place-items: center;
}
</style>
