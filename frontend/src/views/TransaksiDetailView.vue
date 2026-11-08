<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { del, errMsg, fieldErrors, get, post, put } from '@/api/http'
import BatalDialog from '@/components/BatalDialog.vue'
import BayarDialog from '@/components/BayarDialog.vue'
import BIcon from '@/components/BIcon.vue'
import KasirGrid from '@/components/kasir/KasirGrid.vue'
import LengkapiMotorDialog from '@/components/LengkapiMotorDialog.vue'
import SatuanDialog from '@/components/kasir/SatuanDialog.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusTag from '@/components/StatusTag.vue'
import { useAuthStore } from '@/stores/auth'
import { METODE, qty as fmtQty, rp, tglJam } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()

const id = computed(() => route.params.id)
const t = ref(null)
const loadError = ref('')
const pending = ref(0)
const mekaniks = ref([])
let orig = null // salinan data server terakhir, untuk mendeteksi perubahan saat blur

const bayarDialog = ref(false)
const batalDialog = ref(false)
const ubahDialog = ref(false)
const motorDialog = ref(false)
const ubah = ref({ keluhan: '', km: null })

const servis = computed(() => t.value?.jenis === 'servis')
const edit = computed(() => t.value?.terbuka)
const kosong = computed(() => !t.value?.jasas.length && !t.value?.spareparts.length)
const LANGKAH = [
  { k: 'antri', l: 'Antri' },
  { k: 'dikerjakan', l: 'Dikerjakan' },
  { k: 'selesai', l: 'Selesai' },
  { k: 'lunas', l: 'Lunas' },
]
const langkahKe = computed(() => LANGKAH.findIndex((x) => x.k === t.value?.status))
const pemilik = computed(() => t.value?.pelanggan || t.value?.kendaraan?.pelanggan)
const motorLengkap = computed(() => !!(t.value?.kendaraan?.merk && t.value?.kendaraan?.tipe && pemilik.value))
const namaMotor = computed(() => [t.value?.kendaraan?.merk, t.value?.kendaraan?.tipe].filter(Boolean).join(' '))
const laba = computed(() => (t.value && auth.isOwner ? t.value.total - t.value.total_hpp - t.value.total_komisi : null))

function terima(data) {
  t.value = data
  orig = JSON.parse(JSON.stringify(data))
}

async function muat() {
  loadError.value = ''
  try {
    terima((await get(`/transaksi/${id.value}`)).data)
    if (route.query.bayar) {
      router.replace({ path: route.path })
      if (t.value.terbuka && !kosong.value) bayarDialog.value = true
    }
  } catch (e) {
    loadError.value = e.response?.status === 404 ? 'Transaksi tidak ditemukan.' : errMsg(e)
  }
}

onMounted(async () => {
  muat()
  try {
    mekaniks.value = (await get('/cari/mekanik')).data
  } catch {
    /* dropdown mekanik kosong; pesan error muncul saat menambah jasa */
  }
})
watch(id, muat)

/** Jalankan perubahan ke server; kalau gagal, tampilkan pesan dan kembalikan tampilan ke data server. */
async function kirim(fn, pesan) {
  pending.value++
  try {
    const res = await fn()
    terima(res.data)
    if (pesan) toast.add({ severity: 'success', summary: pesan, life: 1800 })
    return true
  } catch (e) {
    const err = fieldErrors(e)
    toast.add({ severity: 'error', summary: 'Tidak bisa disimpan', detail: Object.values(err)[0] || errMsg(e), life: 6000 })
    await muat()
    return false
  } finally {
    pending.value--
  }
}

// ---------------------------------------------------------------- jasa
const jasaInput = ref('')
const jasaHasil = ref([])
const jasaForm = ref({ jasa_id: null, harga: null, diskon: 0, mekanik_id: null })
const jasaNama = computed(() => (typeof jasaInput.value === 'string' ? jasaInput.value.trim() : jasaInput.value?.nama || ''))
const jasaBisa = computed(() => jasaNama.value && jasaForm.value.harga != null && jasaForm.value.mekanik_id)

async function cariJasa(e) {
  try {
    jasaHasil.value = (await get('/cari/jasa', { q: e.query })).data
  } catch {
    jasaHasil.value = []
  }
}
function pilihJasa(e) {
  jasaForm.value.jasa_id = e.value.id
  jasaForm.value.harga = e.value.harga_standar
}
watch(jasaInput, (v) => {
  if (typeof v === 'string') jasaForm.value.jasa_id = null // diketik bebas: bukan dari daftar
})
watch(mekaniks, (m) => {
  if (!jasaForm.value.mekanik_id && m.length === 1) jasaForm.value.mekanik_id = m[0].id
})

async function tambahJasa() {
  const f = jasaForm.value
  const ok = await kirim(() => post(`/transaksi/${id.value}/jasa`, { jasa_id: f.jasa_id, nama: jasaNama.value, harga: f.harga, diskon: f.diskon || 0, mekanik_id: f.mekanik_id }), 'Jasa ditambahkan')
  if (ok) {
    jasaInput.value = ''
    Object.assign(f, { jasa_id: null, harga: null, diskon: 0 }) // mekanik tetap: biasanya satu mekanik mengerjakan beberapa jasa
  }
}

const asli = (list, row) => orig?.[list]?.find((x) => x.id === row.id)
function simpanJasa(row) {
  const a = asli('jasas', row)
  if (a && a.harga === row.harga && a.diskon === row.diskon && a.mekanik_id === row.mekanik_id) return
  kirim(() => put(`/transaksi/${id.value}/jasa/${row.id}`, { harga: row.harga ?? 0, diskon: row.diskon ?? 0, mekanik_id: row.mekanik_id }))
}
const hapusJasa = (row) => kirim(() => del(`/transaksi/${id.value}/jasa/${row.id}`), 'Jasa dihapus')
const opsiMekanik = (row) => (mekaniks.value.some((m) => m.id === row.mekanik_id) ? mekaniks.value : [...mekaniks.value, row.mekanik])

// ---------------------------------------------------------------- sparepart
const spInput = ref('')
const spAuto = ref()
const spHasil = ref([])
const spForm = ref({ satuan_id: null, qty: 1, diskon: 0 })
const spPilih = computed(() => (typeof spInput.value === 'object' ? spInput.value : null))
const satuanPilih = computed(() => spPilih.value?.satuans.find((s) => s.satuan_id === spForm.value.satuan_id))
const hargaBaris = computed(() => (satuanPilih.value ? Math.round((spForm.value.qty || 0) * satuanPilih.value.harga_jual) : 0))

const cariSp = async (q) => (await get('/cari/sparepart', { q })).data
async function carikan(e) {
  try {
    spHasil.value = await cariSp(e.query)
  } catch {
    spHasil.value = []
  }
}
function pilihSp(e) {
  const sp = e.value
  spForm.value = { satuan_id: sp.satuans.find((s) => s.satuan_id === sp.satuan_dasar_id)?.satuan_id ?? sp.satuans[0]?.satuan_id, qty: 1, diskon: 0 }
  // Satu satuan saja: langsung masuk 1 buah (jumlah dan diskon bisa diubah di tabel). Beberapa satuan: pilih satuan dulu.
  if (sp.satuans.length === 1) tambahSp(sp, spForm.value)
}
const stokHabis = (s) => Number(s.stok) <= 0
const stokTipis = (s) => !stokHabis(s) && s.stok_min > 0 && Number(s.stok) <= s.stok_min
const hargaDasar = (s) => (s.satuans.find((x) => x.satuan_id === s.satuan_dasar_id) || s.satuans[0])?.harga_jual

async function tambahSp(sp = spPilih.value, f = spForm.value, fokus = true) {
  const ok = await kirim(() => post(`/transaksi/${id.value}/sparepart`, { sparepart_id: sp.id, satuan_id: f.satuan_id, qty: f.qty, diskon: f.diskon || 0 }), `${sp.nama} ditambahkan`)
  if (ok) {
    spInput.value = ''
    spHasil.value = []
    spAuto.value?.hide()
    spForm.value = { satuan_id: null, qty: 1, diskon: 0 }
    if (fokus) document.getElementById('cari-sp')?.focus()
  }
  return ok
}

// ---- pilih dari daftar kartu (sama seperti layar kasir): dialog tetap terbuka supaya bisa menambah beberapa barang
const dlgGrid = ref(false)
const dlgSatuan = ref(false)
const satuanSp = ref(null)
const gridRef = ref()

async function tambahDariGrid(sp, satuan, qty = 1) {
  // stok di kartu ikut disegarkan, supaya angkanya tidak basi setelah barang masuk
  if (await tambahSp(sp, { satuan_id: satuan.satuan_id, qty, diskon: 0 }, false)) gridRef.value?.segarkan([sp.id])
}
/** Satu satuan atau hasil scan barcode langsung masuk; barang beberapa satuan menanyakan satuan dulu. */
function pilihDariGrid(sp, satuanBarcode) {
  if (satuanBarcode) return tambahDariGrid(sp, satuanBarcode)
  if (sp.satuans.length > 1) {
    satuanSp.value = sp
    return (dlgSatuan.value = true)
  }
  return tambahDariGrid(sp, sp.satuans[0])
}

/** Enter di kotak cari: hasil tunggal (mis. hasil scan barcode) langsung ditambahkan 1 satuan sesuai barcode. */
async function enterSp(e) {
  const q = e.target.value.trim()
  if (!q || spPilih.value) return
  const hasil = await cariSp(q).catch(() => [])
  if (hasil.length !== 1) return
  const sp = hasil[0]
  const sat = sp.satuans.find((s) => s.barcode === q) || sp.satuans.find((s) => s.satuan_id === sp.satuan_dasar_id) || sp.satuans[0]
  await tambahSp(sp, { satuan_id: sat.satuan_id, qty: 1, diskon: 0 })
}

function simpanSp(row) {
  const a = asli('spareparts', row)
  if (a && a.qty === row.qty && a.diskon === row.diskon) return
  if (!row.qty) return muat()
  kirim(() => put(`/transaksi/${id.value}/sparepart/${row.id}`, { qty: row.qty, diskon: row.diskon ?? 0 }))
}
const hapusSp = (row) => kirim(() => del(`/transaksi/${id.value}/sparepart/${row.id}`), 'Barang dihapus')

// ---------------------------------------------------------------- status & aksi
const maju = computed(() => ({ antri: { s: 'dikerjakan', l: 'Mulai Dikerjakan', i: 'pi pi-play' }, dikerjakan: { s: 'selesai', l: 'Tandai Selesai', i: 'pi pi-check-circle' } })[t.value?.status])
const ubahStatus = (s) => kirim(() => post(`/transaksi/${id.value}/status`, { status: s }), 'Status diperbarui')

function bukaUbah() {
  ubah.value = { keluhan: t.value.keluhan || '', km: t.value.km }
  ubahDialog.value = true
}
async function simpanUbah() {
  if (await kirim(() => put(`/transaksi/${id.value}`, { keluhan: ubah.value.keluhan || null, km: ubah.value.km }), 'Data diperbarui')) ubahDialog.value = false
}

const cetak = () => window.open(`/cetak/nota/${id.value}?print=1`, '_blank')
const sudahBayar = (data) => terima(data)
const kirimBayar = async (metode, dibayar) => (await post(`/transaksi/${id.value}/bayar`, { metode_bayar: metode, dibayar })).data
</script>

<template>
  <PageHeader :title="t ? t.nomor : 'Transaksi'" back="/transaksi" :subtitle="t ? tglJam(t.created_at) : ''">
    <StatusTag v-if="t" :status="t.status" :jenis="t.jenis" class="head-tag" />
  </PageHeader>

  <Message v-if="loadError" severity="error" :closable="false">
    {{ loadError }} <a href="#" class="underline" @click.prevent="muat">Coba lagi</a>
  </Message>

  <div v-else-if="!t" class="layout"><Skeleton height="420px" border-radius="12px" /><Skeleton height="320px" border-radius="12px" /></div>

  <div v-else class="layout">
    <!-- ============ kolom kiri ============ -->
    <div class="main">
      <!-- info -->
      <section class="panel card">
        <div v-if="servis && t.status !== 'batal'" class="stepper" aria-label="Tahap pengerjaan">
          <div v-for="(l, i) in LANGKAH" :key="l.k" class="step" :class="{ done: i < langkahKe || t.status === 'lunas', now: i === langkahKe && t.status !== 'lunas' }">
            <span class="dot"><i v-if="i < langkahKe || t.status === 'lunas'" class="pi pi-check" /><template v-else>{{ i + 1 }}</template></span>
            <span class="lbl">{{ l.l }}</span>
          </div>
        </div>
        <Message v-if="t.status === 'batal'" severity="error" :closable="false">
          <strong>Transaksi dibatalkan</strong> oleh {{ t.batal_user?.nama }} pada {{ tglJam(t.batal_at) }}.<br />Alasan: {{ t.batal_alasan }}
        </Message>

        <div v-if="t.kendaraan" class="motor-head">
          <span class="plat">{{ t.kendaraan.plat }}</span>
          <span class="nama-motor" :class="{ muted: !namaMotor }">{{ namaMotor || 'Data motor belum lengkap' }}</span>
          <Button v-if="!motorLengkap && t.status !== 'batal'" label="Lengkapi data" icon="pi pi-id-card" text size="small" @click="motorDialog = true" />
        </div>

        <dl class="info">
          <div v-if="t.kendaraan">
            <dt><i class="pi pi-user" /> Pemilik</dt>
            <dd><template v-if="pemilik">{{ pemilik.nama }}<template v-if="pemilik.telepon"> · {{ pemilik.telepon }}</template></template><span v-else class="muted">Belum diisi</span></dd>
          </div>
          <div v-else>
            <dt><i class="pi pi-user" /> Pembeli</dt>
            <dd>{{ t.pelanggan?.nama || 'Pembeli umum' }}</dd>
          </div>
          <div v-if="servis && t.kendaraan">
            <dt><i class="pi pi-gauge" /> Kilometer</dt>
            <dd><template v-if="t.km != null">{{ fmtQty(t.km) }} km</template><span v-else class="muted">Belum dicatat</span></dd>
          </div>
          <div>
            <dt><i class="pi pi-id-card" /> Kasir</dt>
            <dd>{{ t.user?.nama }}</dd>
          </div>
        </dl>

        <div v-if="servis" class="keluhan" :class="{ kosong: !t.keluhan }">
          <i class="pi pi-comment" />
          <div>
            <small>Keluhan</small>
            <p>{{ t.keluhan || 'Tidak ada keluhan dicatat' }}</p>
          </div>
        </div>
        <div v-if="servis && edit && t.status !== 'batal'" class="ubah-baris">
          <Button label="Ubah keluhan / KM" icon="pi pi-pencil" text size="small" @click="bukaUbah" />
        </div>
      </section>

      <!-- jasa -->
      <section v-if="servis" class="panel card">
        <header class="card-head">
          <span class="card-ico"><BIcon name="servis" /></span>
          <h2>Jasa</h2>
          <span class="muted n">{{ t.jasas.length }} item</span>
        </header>

        <div v-if="t.jasas.length" class="tbl" role="table" aria-label="Daftar jasa">
          <div class="tr th jasa" role="row">
            <span>Jasa</span><span>Mekanik</span><span class="r">Harga</span><span class="r">Diskon</span><span class="r">Subtotal</span><span />
          </div>
          <div v-for="j in t.jasas" :key="j.id" class="tr jasa" role="row">
            <span class="nm" data-l="Jasa">{{ j.nama }}<small v-if="auth.isOwner && j.komisi != null" class="muted"> Komisi {{ rp(j.komisi) }} ({{ fmtQty(j.komisi_persen) }}%)</small></span>
            <span data-l="Mekanik">
              <Select v-if="edit" v-model="j.mekanik_id" :options="opsiMekanik(j)" option-label="nama" option-value="id" fluid size="small" aria-label="Mekanik" @change="simpanJasa(j)" />
              <template v-else>{{ j.mekanik?.nama }}</template>
            </span>
            <span class="r" data-l="Harga">
              <InputNumber v-if="edit" v-model="j.harga" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid size="small" aria-label="Harga" @blur="simpanJasa(j)" @keydown.enter="$event.target.blur()" />
              <span v-else class="num">{{ rp(j.harga) }}</span>
            </span>
            <span class="r" data-l="Diskon">
              <InputNumber v-if="edit" v-model="j.diskon" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid size="small" aria-label="Diskon" @blur="simpanJasa(j)" @keydown.enter="$event.target.blur()" />
              <span v-else class="num">{{ j.diskon ? rp(j.diskon) : '-' }}</span>
            </span>
            <span class="r sub" data-l="Subtotal"><strong class="num">{{ rp(j.subtotal) }}</strong></span>
            <span class="r"><Button v-if="edit" icon="pi pi-trash" text rounded severity="danger" size="small" aria-label="Hapus jasa" @click="hapusJasa(j)" /></span>
          </div>
        </div>
        <p v-else-if="!edit" class="muted kosong-baris">Tidak ada jasa.</p>
        <p v-else class="muted kosong-baris">Belum ada jasa. Tambahkan di bawah.</p>

        <form v-if="edit" class="tambah" @submit.prevent="tambahJasa">
          <div class="field grow">
            <label for="jasa">Tambah jasa</label>
            <AutoComplete
              v-model="jasaInput"
              :suggestions="jasaHasil"
              option-label="nama"
              dropdown
              dropdown-mode="blank"
              fluid
              input-id="jasa"
              placeholder="Pilih dari daftar atau ketik nama jasa"
              @complete="cariJasa"
              @option-select="pilihJasa"
            >
              <template #option="{ option }">
                <div class="opt"><span>{{ option.nama }}</span><small class="muted">{{ option.kategori }} · {{ rp(option.harga_standar) }}</small></div>
              </template>
            </AutoComplete>
          </div>
          <div class="field">
            <label for="mek">Mekanik</label>
            <Select v-model="jasaForm.mekanik_id" :options="mekaniks" option-label="nama" option-value="id" input-id="mek" placeholder="Pilih" fluid />
          </div>
          <div class="field">
            <label for="hj">Harga</label>
            <InputNumber v-model="jasaForm.harga" input-id="hj" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid />
          </div>
          <div class="field">
            <label for="dj">Diskon</label>
            <InputNumber v-model="jasaForm.diskon" input-id="dj" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid />
          </div>
          <Button type="submit" label="Tambah" icon="pi pi-plus" :disabled="!jasaBisa || pending > 0" class="btn-tambah" />
        </form>
      </section>

      <!-- sparepart -->
      <section class="panel card">
        <header class="card-head">
          <span class="card-ico"><BIcon name="sparepart" /></span>
          <h2>Sparepart</h2>
          <span class="muted n">{{ t.spareparts.length }} item</span>
        </header>

        <div v-if="t.spareparts.length" class="tbl" role="table" aria-label="Daftar sparepart">
          <div class="tr th part" role="row">
            <span>Barang</span><span class="r">Jumlah</span><span class="r">Harga</span><span class="r">Diskon</span><span class="r">Subtotal</span><span />
          </div>
          <div v-for="s in t.spareparts" :key="s.id" class="tr part" role="row">
            <span class="nm" data-l="Barang">{{ s.nama }}<small class="muted"> per {{ s.satuan }}<template v-if="s.isi !== 1"> (isi {{ fmtQty(s.isi) }})</template></small></span>
            <span class="r" data-l="Jumlah">
              <InputNumber v-if="edit" v-model="s.qty" :min="0" :max-fraction-digits="2" locale="id-ID" fluid size="small" aria-label="Jumlah" @blur="simpanSp(s)" @keydown.enter="$event.target.blur()" />
              <span v-else class="num">{{ fmtQty(s.qty) }} {{ s.satuan }}</span>
            </span>
            <span class="r num" data-l="Harga">{{ rp(s.harga) }}</span>
            <span class="r" data-l="Diskon">
              <InputNumber v-if="edit" v-model="s.diskon" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid size="small" aria-label="Diskon" @blur="simpanSp(s)" @keydown.enter="$event.target.blur()" />
              <span v-else class="num">{{ s.diskon ? rp(s.diskon) : '-' }}</span>
            </span>
            <span class="r sub" data-l="Subtotal"><strong class="num">{{ rp(s.subtotal) }}</strong></span>
            <span class="r"><Button v-if="edit" icon="pi pi-trash" text rounded severity="danger" size="small" aria-label="Hapus barang" @click="hapusSp(s)" /></span>
          </div>
        </div>
        <p v-else-if="!edit" class="muted kosong-baris">Tidak ada sparepart.</p>
        <p v-else class="muted kosong-baris">Belum ada barang. Cari di bawah atau scan barcode lalu tekan Enter.</p>

        <form v-if="edit" class="tambah sp" @submit.prevent="tambahSp()">
          <div class="field grow">
            <div class="lbl-baris">
              <label for="cari-sp">Tambah barang</label>
              <Button label="Lihat semua barang" icon="pi pi-th-large" size="small" text @click="dlgGrid = true" />
            </div>
            <AutoComplete
              ref="spAuto"
              v-model="spInput"
              :suggestions="spHasil"
              option-label="nama"
              dropdown
              dropdown-mode="blank"
              fluid
              input-id="cari-sp"
              placeholder="Cari nama, kode, atau scan barcode"
              :option-disabled="stokHabis"
              @complete="carikan"
              @option-select="pilihSp"
              @keydown.enter.prevent="enterSp"
            >
              <template #option="{ option }">
                <div class="opt-sp">
                  <div class="opt">
                    <span>{{ option.nama }} <small v-if="option.merk" class="muted">{{ option.merk }}</small></span>
                    <small class="muted">{{ option.kode }}<template v-if="option.lokasi"> · Rak {{ option.lokasi }}</template></small>
                  </div>
                  <div class="opt-kanan">
                    <strong class="num">{{ rp(hargaDasar(option)) }}</strong>
                    <small v-if="stokHabis(option)" class="habis">Stok habis</small>
                    <small v-else :class="stokTipis(option) ? 'tipis' : 'muted'">Stok {{ fmtQty(option.stok) }} {{ option.satuan_dasar }}</small>
                  </div>
                </div>
              </template>
              <template #empty>Barang tidak ditemukan atau stok habis.</template>
            </AutoComplete>
            <small v-if="spPilih" class="hint">Barang ini punya beberapa satuan. Pilih satuan dan jumlah, lalu tekan Tambah.</small>
            <small v-else class="hint">Pilih barang untuk langsung menambah 1. Jumlah dan diskon bisa diubah di tabel.</small>
          </div>
          <div class="field">
            <label for="sat">Satuan</label>
            <Select v-model="spForm.satuan_id" :options="spPilih?.satuans || []" option-value="satuan_id" input-id="sat" :disabled="!spPilih" placeholder="Satuan" fluid>
              <template #value="{ value }">{{ spPilih?.satuans.find((s) => s.satuan_id === value)?.nama || 'Satuan' }}</template>
              <template #option="{ option }">{{ option.nama }}<small class="muted"> · isi {{ fmtQty(option.isi) }} · {{ rp(option.harga_jual) }}</small></template>
            </Select>
          </div>
          <div class="field">
            <label for="qty">Jumlah</label>
            <InputNumber v-model="spForm.qty" input-id="qty" :min="0" :max-fraction-digits="2" locale="id-ID" :disabled="!spPilih" fluid />
          </div>
          <div class="field">
            <label for="ds">Diskon</label>
            <InputNumber v-model="spForm.diskon" input-id="ds" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" :disabled="!spPilih" fluid />
          </div>
          <Button type="submit" :label="hargaBaris ? `Tambah ${rp(hargaBaris - (spForm.diskon || 0))}` : 'Tambah'" icon="pi pi-plus" :disabled="!spPilih || !spForm.qty || pending > 0" class="btn-tambah" />
        </form>
      </section>
    </div>

    <!-- ============ ringkasan ============ -->
    <aside class="side">
      <section class="panel ringkas">
        <h2>Ringkasan</h2>
        <dl class="baris">
          <div v-if="servis"><dt>Jasa</dt><dd class="num">{{ rp(t.total_jasa) }}</dd></div>
          <div><dt>Sparepart</dt><dd class="num">{{ rp(t.total_sparepart) }}</dd></div>
          <div v-if="t.total_diskon" class="hemat"><dt>Sudah termasuk diskon</dt><dd class="num">{{ rp(t.total_diskon) }}</dd></div>
        </dl>
        <div class="total"><span>Total</span><strong class="num">{{ rp(t.total) }}</strong></div>

        <template v-if="t.status === 'lunas'">
          <dl class="baris bayar">
            <div><dt>Metode</dt><dd>{{ METODE[t.metode_bayar].label }}</dd></div>
            <div><dt>Dibayar</dt><dd class="num">{{ rp(t.dibayar) }}</dd></div>
            <div v-if="t.metode_bayar === 'tunai'"><dt>Kembalian</dt><dd class="num">{{ rp(t.kembalian) }}</dd></div>
            <div><dt>Waktu</dt><dd>{{ tglJam(t.lunas_at) }}</dd></div>
          </dl>
        </template>

        <div class="aksi">
          <template v-if="edit">
            <Button v-if="servis && maju" :label="maju.l" :icon="maju.i" severity="secondary" outlined fluid :disabled="pending > 0" @click="ubahStatus(maju.s)" />
            <Button label="Bayar" icon="pi pi-wallet" size="large" fluid :disabled="kosong || pending > 0" :loading="pending > 0" @click="bayarDialog = true" />
            <small v-if="kosong" class="muted c">Tambahkan jasa atau sparepart dulu untuk bisa membayar.</small>
          </template>
          <Button v-if="t.status === 'lunas'" label="Cetak Nota" icon="pi pi-print" size="large" fluid @click="cetak" />
          <Button v-if="t.status === 'batal' || t.status === 'lunas'" label="Transaksi Baru" icon="pi pi-plus" severity="secondary" outlined fluid @click="router.push(servis ? '/servis' : '/kasir')" />
          <Button v-if="t.bisa_batal" label="Batalkan transaksi" icon="pi pi-times" text severity="danger" size="small" fluid @click="batalDialog = true" />
        </div>
      </section>

      <section v-if="auth.isOwner && t.total_hpp != null && t.status !== 'batal'" class="panel owner">
        <h3><i class="pi pi-lock" /> Khusus owner</h3>
        <dl class="baris">
          <div><dt>HPP sparepart</dt><dd class="num">{{ rp(t.total_hpp) }}</dd></div>
          <div><dt>Komisi mekanik</dt><dd class="num">{{ rp(t.total_komisi) }}</dd></div>
          <div class="laba"><dt>Laba kotor</dt><dd class="num">{{ rp(laba) }}</dd></div>
        </dl>
      </section>
    </aside>

    <!-- bar bawah di HP -->
    <div v-if="edit" class="mbar">
      <div><small class="muted">Total</small><strong class="num">{{ rp(t.total) }}</strong></div>
      <Button label="Bayar" icon="pi pi-wallet" :disabled="kosong || pending > 0" @click="bayarDialog = true" />
    </div>
  </div>

  <BayarDialog v-if="t" v-model:visible="bayarDialog" :total="t.total" :kirim="kirimBayar" @paid="sudahBayar" />
  <BatalDialog v-if="t" v-model:visible="batalDialog" :transaksi="t" @batal="terima" />
  <LengkapiMotorDialog v-if="t?.kendaraan" v-model:visible="motorDialog" :kendaraan="t.kendaraan" @saved="muat" />

  <!-- pilih barang dari daftar kartu -->
  <Dialog v-model:visible="dlgGrid" modal header="Pilih Barang" :style="{ width: '64rem' }" :breakpoints="{ '1000px': '96vw' }">
    <div class="grid-dlg"><KasirGrid ref="gridRef" tanpa-keranjang @pilih="pilihDariGrid" /></div>
    <template #footer>
      <span class="muted dlg-info">Ketuk barang untuk menambahkannya ke transaksi ini. Dialog tetap terbuka untuk menambah barang lain.</span>
      <Button label="Selesai" icon="pi pi-check" @click="dlgGrid = false" />
    </template>
  </Dialog>
  <SatuanDialog v-model:visible="dlgSatuan" :sparepart="satuanSp" tanpa-keranjang aksi="Tambahkan ke Transaksi" @tambah="tambahDariGrid" />

  <Dialog v-model:visible="ubahDialog" modal header="Ubah Keluhan dan Kilometer" :style="{ width: '30rem' }" :breakpoints="{ '600px': '94vw' }">
    <form class="form-ubah" @submit.prevent="simpanUbah">
      <div class="field">
        <label for="ku">Keluhan</label>
        <Textarea id="ku" v-model="ubah.keluhan" rows="3" auto-resize fluid maxlength="500" />
      </div>
      <div class="field">
        <label for="kmu">Kilometer</label>
        <InputNumber v-model="ubah.km" input-id="kmu" suffix=" km" :min="0" :max-fraction-digits="0" locale="id-ID" fluid />
      </div>
      <div class="aksi-ubah">
        <Button type="button" label="Batal" severity="secondary" outlined @click="ubahDialog = false" />
        <Button type="submit" label="Simpan" icon="pi pi-check" :loading="pending > 0" />
      </div>
    </form>
  </Dialog>
</template>

<style scoped>
.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 16px;
  align-items: start;
}
.main,
.side {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}
.side {
  position: sticky;
  top: 0;
}
.card {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.card-head {
  display: flex;
  align-items: center;
  gap: 12px;
}
.card-head h2 {
  font-size: 1.1rem;
}
.card-head .n {
  margin-left: auto;
}
.card-ico {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
  color: var(--accent-text);
}
.head-tag {
  font-size: 0.95rem;
}

/* tahap pengerjaan */
.stepper {
  display: flex;
  align-items: center;
}
.step {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--p-text-muted-color);
}
.step:not(:last-child)::after {
  content: '';
  flex: 1;
  height: 2px;
  margin: 0 8px;
  background: var(--p-content-border-color);
}
.step.done:not(:last-child)::after {
  background: var(--p-primary-color);
}
.dot {
  width: 28px;
  height: 28px;
  flex: none;
  display: grid;
  place-items: center;
  border-radius: 50%;
  font-size: 0.8rem;
  font-weight: 600;
  border: 2px solid var(--p-content-border-color);
}
.step.done .dot {
  background: var(--p-primary-color);
  border-color: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}
.step.now .dot {
  border-color: var(--p-primary-color);
  color: var(--accent-text);
}
.step.now .lbl {
  color: var(--p-text-color);
  font-weight: 600;
}
.lbl {
  white-space: nowrap;
  font-size: 0.9rem;
}

.motor-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 12px;
}
.nama-motor {
  font-size: 1.1rem;
  font-weight: 600;
}
.info {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px 24px;
  margin: 0;
  padding-top: 14px;
  border-top: 1px solid var(--p-content-border-color);
}
.info dt {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  color: var(--p-text-muted-color);
}
.info dt i {
  font-size: 0.85rem;
}
.info dd {
  margin: 3px 0 0;
  font-weight: 500;
}
.keluhan {
  display: flex;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-text-muted-color) 10%, transparent);
}
.keluhan > i {
  margin-top: 3px;
  color: var(--p-text-muted-color);
}
.keluhan small {
  color: var(--p-text-muted-color);
}
.keluhan p {
  margin: 2px 0 0;
  font-weight: 500;
}
.keluhan.kosong p {
  font-weight: 400;
  color: var(--p-text-muted-color);
}
.plat {
  display: inline-block;
  padding: 1px 8px;
  margin-right: 6px;
  border: 2px solid var(--p-text-color);
  border-radius: 5px;
  font-weight: 700;
  letter-spacing: 0.06em;
}
.ubah-baris {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  margin-top: -8px;
}

/* tabel baris */
.tbl {
  display: flex;
  flex-direction: column;
}
.tr {
  display: grid;
  gap: 12px;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid var(--p-content-border-color);
}
.tr.jasa {
  grid-template-columns: minmax(150px, 2fr) minmax(130px, 1.3fr) minmax(120px, 1.2fr) minmax(110px, 1fr) minmax(100px, 1fr) 40px;
}
.tr.part {
  grid-template-columns: minmax(170px, 2.2fr) minmax(110px, 1fr) minmax(100px, 1fr) minmax(110px, 1fr) minmax(100px, 1fr) 40px;
}
.th {
  padding: 0 0 8px;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
}
.r {
  text-align: right;
}
.nm {
  display: flex;
  flex-direction: column;
  line-height: 1.35;
  font-weight: 500;
}
.nm small {
  font-weight: 400;
}
.kosong-baris {
  margin: 0;
  padding: 8px 0;
}

/* form tambah */
.tambah {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
  gap: 12px;
  align-items: end;
  padding: 14px;
  border: 1px dashed var(--p-content-border-color);
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-primary-color) 3%, transparent);
}
.tambah .grow {
  grid-column: 1 / -1;
}
.btn-tambah {
  white-space: nowrap;
}
.opt {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}
.habis {
  color: var(--p-red-500);
  font-weight: 600;
}
.tipis {
  color: var(--p-orange-500);
  font-weight: 600;
}
.lbl-baris {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
/* Wadah tinggi tetap supaya grid kartu punya area scroll sendiri di dalam dialog. */
.grid-dlg {
  display: grid;
  grid-template-rows: minmax(0, 1fr);
  height: min(62dvh, 560px);
}
.dlg-info {
  margin-right: auto;
  font-size: 0.85rem;
}
.opt-sp {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  width: 100%;
}
.opt-kanan {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex: none;
  line-height: 1.3;
}
.tambah .hint {
  color: var(--p-text-muted-color);
}

/* ringkasan */
.ringkas {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.ringkas h2 {
  font-size: 1.1rem;
}
.baris {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.baris > div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.baris dt {
  color: var(--p-text-muted-color);
}
.baris dd {
  margin: 0;
  font-weight: 500;
  text-align: right;
}
.hemat dd {
  color: var(--p-green-600);
}
.total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding: 14px 0;
  border-top: 1px solid var(--p-content-border-color);
  border-bottom: 1px solid var(--p-content-border-color);
}
.total span {
  font-weight: 600;
}
.total strong {
  font-size: 1.8rem;
  color: var(--accent-text);
}
.bayar {
  padding-bottom: 4px;
}
.aksi {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.c {
  text-align: center;
}
.owner {
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: color-mix(in srgb, var(--p-primary-color) 4%, var(--p-content-background));
}
.owner h3 {
  font-size: 0.9rem;
  color: var(--p-text-muted-color);
  display: flex;
  align-items: center;
  gap: 8px;
}
.laba {
  padding-top: 8px;
  border-top: 1px solid var(--p-content-border-color);
}
.laba dd {
  font-weight: 700;
  color: var(--accent-text);
}

.form-ubah {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.aksi-ubah {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.mbar {
  display: none;
}

/* tablet: ringkasan pindah ke bawah, tabel baris jadi kartu */
@media (max-width: 1100px) {
  .layout {
    grid-template-columns: 1fr;
  }
  .side {
    position: static;
  }
}
@media (max-width: 760px) {
  .tambah {
    grid-template-columns: 1fr 1fr;
  }
  .btn-tambah {
    grid-column: 1 / -1;
  }
  .th {
    display: none;
  }
  .tr.jasa,
  .tr.part {
    grid-template-columns: 1fr 1fr;
    padding: 12px 0;
  }
  .tr .nm {
    grid-column: 1 / -1;
  }
  .tr .r,
  .tr > span:nth-child(2) {
    text-align: left;
  }
  .tr > [data-l]:not(.nm)::before {
    content: attr(data-l);
    display: block;
    font-size: 0.75rem;
    color: var(--p-text-muted-color);
    margin-bottom: 2px;
  }
  .tr > span:last-child {
    grid-column: 1 / -1;
    text-align: right;
  }
  .stepper .lbl {
    display: none;
  }
  .stepper .step.now .lbl {
    display: inline;
  }
  .layout {
    padding-bottom: 76px;
  }
  .mbar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 30;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 16px;
    background: var(--p-content-background);
    border-top: 1px solid var(--p-content-border-color);
    box-shadow: 0 -4px 16px rgb(15 23 42 / 0.08);
  }
  .mbar div {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
  }
  .mbar strong {
    font-size: 1.3rem;
  }
}
</style>
