<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { errMsg, fieldErrors, get, post } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import PageHeader from '@/components/PageHeader.vue'
import PlatInput from '@/components/PlatInput.vue'
import { rp, tglJam } from '@/utils/format'

const router = useRouter()
const toast = useToast()
const confirm = useConfirm()

const data = ref(null)
const error = ref('')
const diperbarui = ref(null)
const baru = ref(null) // kartu yang baru masuk, disorot sebentar
const sibuk = ref(null) // id kartu yang sedang diubah statusnya
const sekarang = ref(Date.now())

const form = ref({ plat: '', keluhan: '' })
const masukBusy = ref(false)
const platInput = ref()

const KOLOM = [
  { k: 'antri', judul: 'Antri', hint: 'Menunggu dikerjakan', ikon: 'pi pi-clock' },
  { k: 'dikerjakan', judul: 'Dikerjakan', hint: 'Sedang di tangan mekanik', ikon: 'pi pi-cog' },
  { k: 'selesai', judul: 'Siap Bayar', hint: 'Selesai, menunggu pembayaran', ikon: 'pi pi-wallet' },
  { k: 'lunas', judul: 'Lunas Hari Ini', hint: 'Sudah dibayar', ikon: 'pi pi-check-circle' },
]
const LANJUT = { antri: { ke: 'dikerjakan', label: 'Mulai', ikon: 'pi pi-play' }, dikerjakan: { ke: 'selesai', label: 'Selesai', ikon: 'pi pi-check' } }

const norm = (s) => String(s || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase()
const terbuka = computed(() => (data.value ? [...data.value.antri, ...data.value.dikerjakan, ...data.value.selesai] : []))
const kosong = computed(() => data.value && !terbuka.value.length && !data.value.lunas.length)
const total = computed(() => ({ motor: terbuka.value.length, omzet: (data.value?.lunas || []).reduce((s, c) => s + c.total, 0) }))

async function muat(diam = false) {
  try {
    data.value = (await get('/papan')).data
    error.value = ''
    diperbarui.value = new Date()
  } catch (e) {
    if (!diam || !data.value) error.value = errMsg(e)
  }
}

let timer
let tick
const lihat = () => document.visibilityState === 'visible' && muat(true)
onMounted(() => {
  muat()
  timer = setInterval(lihat, 20000)
  tick = setInterval(() => (sekarang.value = Date.now()), 30000)
  document.addEventListener('visibilitychange', lihat)
})
onBeforeUnmount(() => {
  clearInterval(timer)
  clearInterval(tick)
  document.removeEventListener('visibilitychange', lihat)
})

// ---------------------------------------------------------------- motor masuk
async function masuk(paksa = false) {
  if (norm(form.value.plat).length < 3) {
    return toast.add({ severity: 'warn', summary: 'Isi plat nomor dulu', detail: 'Cukup ketik plat motor yang masuk.', life: 3500 })
  }
  const ada = terbuka.value.find((c) => norm(c.plat) === norm(form.value.plat))
  if (ada && !paksa) {
    return confirm.require({
      header: 'Motor ini masih ada di papan',
      message: `${ada.plat} sudah tercatat (${ada.nomor}, ${ada.status === 'antri' ? 'antri' : ada.status === 'dikerjakan' ? 'dikerjakan' : 'siap bayar'}). Buka transaksi itu, atau tetap buat yang baru?`,
      icon: 'pi pi-exclamation-triangle',
      acceptProps: { label: 'Buka yang ada' },
      rejectProps: { label: 'Tetap buat baru', severity: 'secondary', outlined: true },
      accept: () => router.push(`/transaksi/${ada.id}`),
      reject: () => masuk(true),
    })
  }

  masukBusy.value = true
  try {
    const res = await post('/transaksi', { jenis: 'servis', plat: form.value.plat, keluhan: form.value.keluhan || null })
    toast.add({ severity: 'success', summary: `${res.data.kendaraan.plat} masuk antrian`, detail: res.data.nomor, life: 3500 })
    form.value = { plat: '', keluhan: '' }
    baru.value = res.data.id
    setTimeout(() => (baru.value = null), 5000)
    await muat(true)
    platInput.value?.$el?.querySelector('input')?.focus()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Belum bisa dicatat', detail: Object.values(fieldErrors(e))[0] || errMsg(e), life: 5000 })
  } finally {
    masukBusy.value = false
  }
}

// ---------------------------------------------------------------- kartu
async function lanjut(c) {
  sibuk.value = c.id
  try {
    await post(`/transaksi/${c.id}/status`, { status: LANJUT[c.status].ke })
    await muat(true)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Tidak bisa mengubah status', detail: Object.values(fieldErrors(e))[0] || errMsg(e), life: 5000 })
  } finally {
    sibuk.value = null
  }
}

function lama(masuk) {
  const m = Math.max(0, Math.floor((sekarang.value - new Date(masuk).getTime()) / 60000))
  if (m < 1) return 'baru saja'
  if (m < 60) return `${m} mnt`
  if (m < 1440) return `${Math.floor(m / 60)} j ${m % 60} mnt`
  return `${Math.floor(m / 1440)} hari`
}
const tingkat = (c) => {
  if (c.status === 'lunas') return ''
  const jam = (sekarang.value - new Date(c.masuk_at).getTime()) / 3600000
  return jam >= 6 ? 'telat' : jam >= 3 ? 'agak' : ''
}
const jam = (d) => new Date(d).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
</script>

<template>
  <PageHeader title="Papan Servis" subtitle="Semua motor yang ada di bengkel, per tahap pengerjaan.">
    <Button severity="secondary" outlined class="btn-kasir" @click="router.push('/kasir')">
      <BIcon name="jual" :size="18" />
      <span>Kasir Sparepart</span>
    </Button>
    <Button label="Form lengkap" icon="pi pi-file-edit" severity="secondary" text @click="router.push('/transaksi/servis/baru')" />
  </PageHeader>

  <!-- motor masuk -->
  <form class="panel masuk" @submit.prevent="masuk()">
    <span class="masuk-ico"><BIcon name="motor" :size="26" /></span>
    <div class="masuk-form">
      <div class="f-plat">
        <label for="plat-masuk">Motor masuk</label>
        <PlatInput ref="platInput" v-model:plat="form.plat" input-id="plat-masuk" size="large" autofocus placeholder="Ketik plat motor, lalu Enter" @enter="masuk()" />
      </div>
      <div class="f-keluhan">
        <label for="keluhan-masuk">Keluhan <span class="muted">(boleh dikosongkan)</span></label>
        <InputText id="keluhan-masuk" v-model="form.keluhan" size="large" fluid maxlength="500" placeholder="Mis. ganti oli, rem bunyi" autocomplete="off" />
      </div>
      <Button type="submit" label="Catat" icon="pi pi-plus" size="large" :loading="masukBusy" class="f-btn" />
    </div>
    <p class="masuk-hint muted">Cukup plat saja. Nama pemilik dan tipe motor bisa dilengkapi nanti.</p>
  </form>

  <Message v-if="error" severity="error" :closable="false" class="mb-4">
    {{ error }} <a href="#" class="underline" @click.prevent="muat()">Coba lagi</a>
  </Message>

  <div class="bar-info">
    <span v-if="data"><strong>{{ total.motor }}</strong> motor di bengkel<template v-if="data.lunas.length"> · <strong>{{ rp(total.omzet) }}</strong> dari {{ data.lunas.length }} servis lunas hari ini</template></span>
    <span class="muted segar">
      <template v-if="diperbarui">Diperbarui {{ jam(diperbarui) }}</template>
      <Button icon="pi pi-refresh" text rounded size="small" severity="secondary" aria-label="Muat ulang" @click="muat()" />
    </span>
  </div>

  <div v-if="!data && !error" class="papan"><Skeleton v-for="i in 4" :key="i" height="260px" border-radius="12px" /></div>

  <div v-else-if="kosong" class="panel kosong">
    <BIcon name="motor" :size="44" />
    <strong>Belum ada motor di bengkel</strong>
    <span class="muted">Ketik plat motor yang masuk di kotak di atas, lalu tekan Enter.</span>
  </div>

  <div v-else-if="data" class="papan">
    <section v-for="kol in KOLOM" :key="kol.k" class="kolom" :class="kol.k">
      <header class="k-head">
        <i :class="kol.ikon" />
        <div>
          <strong>{{ kol.judul }}</strong>
          <small class="muted">{{ kol.hint }}</small>
        </div>
        <span class="jml">{{ data[kol.k].length }}</span>
      </header>

      <div class="k-isi">
        <article
          v-for="c in data[kol.k]"
          :key="c.id"
          class="kartu"
          :class="[kol.k, { baru: baru === c.id }]"
          tabindex="0"
          :aria-label="`${c.plat}, ${kol.judul}`"
          @click="router.push(`/transaksi/${c.id}`)"
          @keydown.enter="router.push(`/transaksi/${c.id}`)"
        >
          <div class="atas">
            <span class="plat">{{ c.plat }}</span>
            <span v-if="kol.k !== 'lunas'" class="lama" :class="tingkat(c)"><i class="pi pi-stopwatch" /> {{ lama(c.masuk_at) }}</span>
            <span v-else class="lama">{{ jam(c.lunas_at) }}</span>
          </div>
          <div class="motor" :class="{ muted: !c.motor }">{{ c.motor || 'Data motor belum lengkap' }}</div>
          <div v-if="c.pemilik" class="pemilik muted"><i class="pi pi-user" /> {{ c.pemilik }}</div>
          <p v-if="c.keluhan && kol.k !== 'lunas'" class="keluhan">{{ c.keluhan }}</p>

          <div class="foot">
            <span v-if="c.mekanik.length" class="mek"><BIcon name="mekanik" :size="15" /> {{ c.mekanik.join(', ') }}</span>
            <span v-else-if="kol.k !== 'lunas'" class="mek muted">Belum ada jasa</span>
            <strong class="num tot">{{ c.jumlah_item ? rp(c.total) : '-' }}</strong>
          </div>

          <div v-if="kol.k !== 'lunas'" class="aksi" @click.stop>
            <Button v-if="LANJUT[c.status]" :label="LANJUT[c.status].label" :icon="LANJUT[c.status].ikon" size="small" severity="secondary" outlined :loading="sibuk === c.id" @click="lanjut(c)" />
            <Button v-if="c.jumlah_item" label="Bayar" icon="pi pi-wallet" size="small" :severity="c.status === 'selesai' ? undefined : 'secondary'" :outlined="c.status !== 'selesai'" @click="router.push(`/transaksi/${c.id}?bayar=1`)" />
            <Button v-else label="Isi jasa" icon="pi pi-pencil" size="small" severity="secondary" outlined @click="router.push(`/transaksi/${c.id}`)" />
          </div>
        </article>

        <p v-if="!data[kol.k].length" class="k-kosong muted">Tidak ada motor</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.btn-kasir {
  gap: 8px;
}
.masuk {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 4px 16px;
  align-items: center;
  padding: 18px 20px;
  margin-bottom: 16px;
  border-color: color-mix(in srgb, var(--p-primary-color) 35%, var(--p-content-border-color));
  background: color-mix(in srgb, var(--p-primary-color) 4%, var(--p-content-background));
}
.masuk-ico {
  width: 52px;
  height: 52px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}
.masuk-form {
  display: grid;
  grid-template-columns: minmax(200px, 1fr) minmax(200px, 1.2fr) auto;
  gap: 12px;
  align-items: end;
}
.masuk-form label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  font-size: 0.9rem;
}
.masuk-hint {
  grid-column: 2;
  margin: 4px 0 0;
  font-size: 0.88rem;
}
.bar-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}
.segar {
  display: flex;
  align-items: center;
  font-size: 0.88rem;
}

.papan {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
  gap: 14px;
  align-items: start;
}
.kolom {
  --warna: var(--p-surface-400);
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  background: color-mix(in srgb, var(--p-text-color) 3.5%, transparent);
  border-top: 4px solid var(--warna);
  min-width: 0;
}
.kolom.antri { --warna: var(--p-orange-500); }
.kolom.dikerjakan { --warna: var(--p-sky-500); }
.kolom.selesai { --warna: var(--p-green-500); }
.kolom.lunas { --warna: var(--p-surface-400); }
.k-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px 8px;
}
.k-head > i {
  font-size: 1.1rem;
  color: var(--warna);
}
.k-head div {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}
.jml {
  margin-left: auto;
  min-width: 28px;
  padding: 2px 8px;
  border-radius: 999px;
  text-align: center;
  font-weight: 700;
  background: color-mix(in srgb, var(--warna) 18%, transparent);
}
.k-isi {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 4px 10px 12px;
}
.k-kosong {
  margin: 12px 0 8px;
  text-align: center;
  font-size: 0.9rem;
}

.kartu {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--p-content-background);
  border: 1px solid var(--p-content-border-color);
  border-left: 4px solid var(--warna);
  box-shadow: var(--shadow-sm);
  cursor: pointer;
  transition: transform 0.12s, box-shadow 0.12s;
}
.kartu:hover,
.kartu:focus-visible {
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgb(15 23 42 / 0.12);
  outline: none;
}
.kartu:focus-visible {
  border-color: var(--p-primary-color);
}
.kartu.lunas {
  opacity: 0.8;
  gap: 4px;
  padding: 9px 12px;
}
.kartu.baru {
  animation: sorot 1.2s ease-in-out 3;
}
@keyframes sorot {
  50% {
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--p-primary-color) 40%, transparent);
  }
}
.atas {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.plat {
  padding: 2px 10px;
  border: 2px solid var(--p-text-color);
  border-radius: 6px;
  font-weight: 700;
  font-size: 1.05rem;
  letter-spacing: 0.08em;
}
.lama {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.82rem;
  color: var(--p-text-muted-color);
}
.lama.agak {
  color: var(--p-orange-600);
  font-weight: 600;
}
.lama.telat {
  color: var(--p-red-500);
  font-weight: 700;
}
.motor {
  font-weight: 600;
}
.pemilik {
  font-size: 0.88rem;
}
.keluhan {
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 0.9rem;
  color: var(--p-text-muted-color);
}
.foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 2px;
  font-size: 0.88rem;
}
.mek {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.tot {
  flex: none;
}
.aksi {
  display: flex;
  gap: 8px;
  margin-top: 4px;
}
.aksi > * {
  flex: 1;
}

.kosong {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 48px 16px;
  text-align: center;
  color: var(--p-text-muted-color);
}
.kosong strong {
  color: var(--p-text-color);
  font-size: 1.1rem;
}

@media (max-width: 900px) {
  .masuk {
    grid-template-columns: 1fr;
  }
  .masuk-ico {
    display: none;
  }
  .masuk-form {
    grid-template-columns: 1fr;
  }
  .masuk-hint {
    grid-column: 1;
  }
  .f-btn {
    width: 100%;
  }
}
</style>
