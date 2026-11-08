<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { errMsg, get } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import PageHeader from '@/components/PageHeader.vue'
import PlatInput from '@/components/PlatInput.vue'
import StatCard from '@/components/StatCard.vue'
import { qty, rp, STATUS, tgl } from '@/utils/format'

const route = useRoute()
const plat = ref('')
const d = ref(null)
const memuat = ref(false)
const error = ref('')
const tidakAda = ref(false)

async function muat(id) {
  memuat.value = true
  error.value = ''
  tidakAda.value = false
  try {
    d.value = (await get(`/kendaraan/${id}/riwayat`)).data
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    memuat.value = false
  }
}

function pilih(k) {
  if (k) muat(k.id)
  else d.value = null
}

// Dibuka dari tautan /riwayat?plat=...: cari motor dengan plat persis itu.
onMounted(async () => {
  const q = String(route.query.plat || '').toUpperCase().replace(/[^A-Z0-9]/g, '')
  if (!q) return
  plat.value = q
  memuat.value = true
  try {
    const k = (await get('/cari/kendaraan', { q })).data.find((x) => x.plat === q)
    if (k) await muat(k.id)
    else tidakAda.value = true
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    memuat.value = false
  }
})

const bagian = (t) => {
  const x = new Date(t)
  return { hari: x.getDate(), bulan: x.toLocaleDateString('id-ID', { month: 'short' }), tahun: x.getFullYear() }
}
const motor = (k) => [k.merk, k.tipe, k.tahun, k.warna].filter(Boolean).join(' ')
</script>

<template>
  <PageHeader title="Riwayat Servis" subtitle="Cari motor dengan plat nomor untuk melihat semua servis sebelumnya." />

  <div class="panel cari">
    <PlatInput v-model:plat="plat" placeholder="Ketik plat nomor atau nama pemilik..." @pilih="pilih" />
  </div>

  <Message v-if="error" severity="error" :closable="false" class="mt">{{ error }}</Message>
  <Message v-else-if="tidakAda" severity="info" :closable="false" class="mt">Motor dengan plat {{ plat }} belum pernah masuk bengkel.</Message>
  <div v-else-if="memuat" class="susun"><Skeleton height="96px" border-radius="12px" /><Skeleton height="160px" border-radius="12px" /></div>

  <div v-else-if="!d" class="panel kosong">
    <BIcon name="motor" :size="40" />
    <strong>Pilih motor dulu</strong>
    <span class="muted">Ketik plat nomor di atas, lalu pilih motornya dari daftar.</span>
  </div>

  <div v-else class="susun">
    <section class="stats">
      <StatCard icon="motor" :label="d.kendaraan.pemilik ? `Milik ${d.kendaraan.pemilik}` : 'Pemilik belum dicatat'" :value="d.kendaraan.plat" :hint="[motor(d.kendaraan), d.kendaraan.telepon].filter(Boolean).join(' · ') || 'Data motor belum lengkap'" />
      <StatCard icon="servis" label="Jumlah kunjungan" :value="d.kunjungan" hint="Servis yang tidak dibatalkan" tone="green" />
      <StatCard icon="riwayat" label="KM terakhir" :value="d.km_terakhir != null ? qty(d.km_terakhir) : '-'" hint="Dari servis terbaru yang mencatat KM" tone="orange" />
    </section>

    <div v-if="!d.riwayat.length" class="panel kosong">
      <strong>Belum ada riwayat servis</strong>
      <span class="muted">Motor ini sudah terdaftar tetapi belum pernah diservis.</span>
    </div>

    <ol v-if="d.riwayat.length" class="garis">
      <li v-for="r in d.riwayat" :key="r.id" class="item">
        <div class="tanggal" :title="tgl(r.tanggal)">
          <strong>{{ bagian(r.tanggal).hari }}</strong>
          <span>{{ bagian(r.tanggal).bulan }}</span>
          <small>{{ bagian(r.tanggal).tahun }}</small>
        </div>

        <article class="panel kunjungan">
          <header>
            <RouterLink :to="`/transaksi/${r.id}`" class="nomor">{{ r.nomor }} <i class="pi pi-angle-right" /></RouterLink>
            <Tag :value="STATUS[r.status].label" :severity="STATUS[r.status].severity" />
          </header>

          <p v-if="r.keluhan" class="keluhan"><i class="pi pi-comment" /><span>{{ r.keluhan }}</span></p>

          <div v-if="r.jasa.length" class="bagian">
            <h4>Pekerjaan</h4>
            <div class="chips"><span v-for="(j, i) in r.jasa" :key="i" class="chip"><i class="pi pi-wrench" />{{ j }}</span></div>
          </div>

          <div v-if="r.sparepart.length" class="bagian">
            <h4>Sparepart</h4>
            <ul class="part">
              <li v-for="(s, i) in r.sparepart" :key="i"><span>{{ s.nama }}</span><b>{{ qty(s.qty) }} {{ s.satuan }}</b></li>
            </ul>
          </div>

          <footer>
            <span v-if="r.km != null" class="km"><i class="pi pi-gauge" />{{ qty(r.km) }} km</span>
            <span v-else />
            <strong class="num total">{{ rp(r.total) }}</strong>
          </footer>
        </article>
      </li>
    </ol>
  </div>
</template>

<style scoped>
.cari {
  padding: 16px;
  margin-bottom: 16px;
}
.mt {
  margin-top: 4px;
}
.susun {
  display: grid;
  gap: 16px;
}
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}
.kosong {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 40px 16px;
  text-align: center;
  color: var(--p-text-muted-color);
}
.kosong strong {
  color: var(--p-text-color);
  font-size: 1.05rem;
}
/* Timeline: garis vertikal di kiri, tiap kunjungan diawali blok tanggal. */
.garis {
  position: relative;
  display: grid;
  gap: 16px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.garis::before {
  content: '';
  position: absolute;
  top: 8px;
  bottom: 8px;
  left: 31px;
  width: 2px;
  background: var(--p-content-border-color);
}
.item {
  position: relative;
  display: grid;
  grid-template-columns: 64px 1fr;
  gap: 16px;
  align-items: start;
}
.tanggal {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 0;
  border-radius: 12px;
  line-height: 1.15;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}
.tanggal strong {
  font-size: 1.5rem;
}
.tanggal span {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
}
.tanggal small {
  font-size: 0.7rem;
  opacity: 0.85;
}
.kunjungan {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px 20px;
}
.kunjungan header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.nomor {
  font-weight: 600;
  color: var(--accent-text);
}
.nomor:hover {
  text-decoration: underline;
}
.keluhan {
  display: flex;
  gap: 10px;
  margin: 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-text-muted-color) 10%, transparent);
  font-style: italic;
}
.keluhan i {
  margin-top: 3px;
  color: var(--p-text-muted-color);
}
.bagian h4 {
  margin: 0 0 6px;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--p-text-muted-color);
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 999px;
  font-weight: 500;
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
  color: var(--accent-text);
}
.chip i {
  font-size: 0.8rem;
}
.part {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.part li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 6px;
  border-bottom: 1px dashed var(--p-content-border-color);
}
.part li:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}
.part b {
  flex: none;
  font-weight: 500;
  color: var(--p-text-muted-color);
}
.kunjungan footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--p-content-border-color);
}
.km {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--p-text-muted-color);
}
.total {
  font-size: 1.25rem;
}
@media (max-width: 560px) {
  .garis::before {
    display: none;
  }
  .item {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .tanggal {
    flex-direction: row;
    justify-content: center;
    gap: 6px;
    width: fit-content;
    padding: 6px 14px;
  }
  .tanggal strong {
    font-size: 1.1rem;
  }
}
</style>
