<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { del, errMsg, fieldErrors, get, post, put } from '@/api/http'
import BarangBaruDialog from '@/components/BarangBaruDialog.vue'
import BIcon from '@/components/BIcon.vue'
import CariBarangDialog from '@/components/CariBarangDialog.vue'
import PageHeader from '@/components/PageHeader.vue'
import RemoteSelect from '@/components/RemoteSelect.vue'
import { useAuthStore } from '@/stores/auth'
import { qty as fmtQty, rp, STATUS_BELI, tgl, tglJam, ymd } from '@/utils/format'

// Satu halaman untuk: terima barang baru, ubah draft, dan melihat penerimaan yang sudah diterima/dibatalkan.
const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()

const id = computed(() => route.params.id)
const baru = computed(() => !id.value)
const p = ref(null)
const loading = ref(!!route.params.id)
const loadError = ref('')
const saving = ref(false)
const errors = ref({})

const edit = computed(() => baru.value || p.value?.status === 'draft')
const form = reactive({ supplier_id: null, supplier_nama: '', no_faktur: '', tanggal_faktur: new Date(), catatan: '' })
const rows = ref([])

const dlg = ref({ terima: false, bayar: false, batal: false })
const terima = reactive({ status_bayar: 'lunas', metode_bayar: 'tunai', sumber: 'laci', jatuh_tempo: null })
const bayar = reactive({ metode_bayar: 'tunai', sumber: 'laci' })
const alasan = ref('')
const dlgError = ref('')

// ---------------------------------------------------------------- hitungan
const kotor = (r) => Math.round((r.qty || 0) * (r.harga || 0))
const sub = (r) => kotor(r) - (r.diskon || 0)
const total = computed(() => rows.value.reduce((s, r) => s + sub(r), 0))
const masuk = (r) => Math.round((r.qty || 0) * r.isi * 100) / 100

// ---------------------------------------------------------------- muat data
const barisDariSparepart = (sp, satuanId = null) => {
  const besar = [...sp.satuans].sort((a, b) => b.isi - a.isi)[0] // barang dibeli dalam satuan besar (box/dus)
  const s = sp.satuans.find((x) => x.satuan_id === satuanId) || besar
  return { k: `${sp.id}:${s.satuan_id}:${Date.now()}:${Math.random()}`, sparepart_id: sp.id, kode: sp.kode, nama: sp.nama, dasar: sp.satuan_dasar, stok: sp.stok, satuans: sp.satuans, satuan_id: s.satuan_id, satuanNama: s.nama, isi: s.isi, qty: 1, harga: null, diskon: 0, beliTerakhir: sp.beli_terakhir, hpp: sp.hpp }
}

async function muat() {
  loading.value = true
  loadError.value = ''
  try {
    p.value = (await get(`/pembelian/${id.value}`)).data
    Object.assign(form, { supplier_id: p.value.supplier_id, no_faktur: p.value.no_faktur || '', tanggal_faktur: new Date(`${p.value.tanggal_faktur}T00:00:00`), catatan: p.value.catatan || '' })

    // Draft bisa diubah: butuh daftar satuan tiap barang. Yang sudah diterima cukup memakai data baris.
    const peta = {}
    if (p.value.status === 'draft') {
      const ids = [...new Set(p.value.items.map((i) => i.sparepart_id))].join(',')
      for (const sp of (await get('/cari/sparepart', { ids, beli: 1 })).data) peta[sp.id] = sp
    }
    rows.value = p.value.items.map((i) => {
      const sp = peta[i.sparepart_id]
      const s = sp?.satuans.find((x) => x.nama === i.satuan)
      return { k: `i${i.id}`, sparepart_id: i.sparepart_id, kode: i.sparepart?.kode, nama: i.sparepart?.nama, dasar: sp?.satuan_dasar, stok: sp?.stok, satuans: sp?.satuans ?? [], satuan_id: s?.satuan_id ?? null, satuanNama: i.satuan, isi: i.isi, qty: i.qty, harga: i.harga, diskon: i.diskon, subtotalServer: i.subtotal, beliTerakhir: sp?.beli_terakhir, hpp: sp?.hpp }
    })
  } catch (e) {
    loadError.value = e.response?.status === 404 ? 'Penerimaan tidak ditemukan.' : errMsg(e)
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (!baru.value) return muat()
  // dari halaman stok: /pembelian/baru?barang=12 langsung menambahkan barang itu
  if (route.query.barang) {
    try {
      const sp = (await get('/cari/sparepart', { ids: route.query.barang, beli: 1 })).data[0]
      if (sp) rows.value.push(barisDariSparepart(sp))
    } catch {
      /* barang bisa ditambahkan manual */
    }
  }
})
watch(id, (v) => v && muat())

// ---------------------------------------------------------------- tambah barang
const cariInput = ref('')
const cariHasil = ref([])
const auto = ref()

const cariSp = async (q) => (await get('/cari/sparepart', { q, beli: 1 })).data
async function cari(e) {
  try {
    cariHasil.value = await cariSp(e.query)
  } catch {
    cariHasil.value = []
  }
}

function tambah(sp, satuanId = null, fokus = true) {
  // barang dan satuan yang sama digabung jadi satu baris
  const ada = rows.value.find((r) => r.sparepart_id === sp.id && r.satuan_id === (satuanId ?? [...sp.satuans].sort((a, b) => b.isi - a.isi)[0].satuan_id))
  if (ada) ada.qty = (ada.qty || 0) + 1
  else rows.value.push(barisDariSparepart(sp, satuanId))
  cariInput.value = ''
  cariHasil.value = []
  auto.value?.hide()
  if (fokus) document.getElementById('cari-barang')?.focus()
}

// Dialog telusuri daftar dan dialog barang baru. Barang yang tidak ketemu bisa langsung dibuatkan (barcode hasil scan ikut terisi).
const dlgBarang = ref(false)
const dlgBaru = ref(false)
const awalBaru = ref({})
const dipilih = computed(() => rows.value.reduce((m, r) => ({ ...m, [r.sparepart_id]: (m[r.sparepart_id] || 0) + 1 }), {}))

function bukaBaru(teks) {
  const t = typeof teks === 'string' ? teks.trim() : ''
  awalBaru.value = /^\d{6,}$/.test(t) ? { nama: '', barcode: t } : { nama: t, barcode: '' }
  dlgBarang.value = false
  dlgBaru.value = true
}
function barangDibuat(sp) {
  tambah(sp, null, false)
  toast.add({ severity: 'success', summary: 'Barang baru ditambahkan', detail: `${sp.nama} masuk ke daftar penerimaan. Isi jumlah dan harga belinya.`, life: 4000 })
}
const pilih = (e) => tambah(e.value)

/** Enter di kotak cari: hasil tunggal (mis. hasil scan barcode) langsung masuk dengan satuan sesuai barcode. */
async function enterCari(e) {
  const q = e.target.value.trim()
  if (!q || typeof cariInput.value === 'object') return
  const hasil = await cariSp(q).catch(() => [])
  if (hasil.length === 1) tambah(hasil[0], hasil[0].satuans.find((s) => s.barcode === q)?.satuan_id ?? null)
  else if (!hasil.length) bukaBaru(q)
}

function gantiSatuan(r) {
  const s = r.satuans.find((x) => x.satuan_id === r.satuan_id)
  if (s) Object.assign(r, { isi: s.isi, satuanNama: s.nama })
}

// ---------------------------------------------------------------- simpan / terima
// Nama yang diketik tanpa memilih dari daftar = supplier baru (dibuat server saat disimpan).
const supplierBaru = computed(() => (form.supplier_id ? '' : form.supplier_nama.trim()))

const badan = () => ({
  supplier_id: form.supplier_id,
  supplier_nama: supplierBaru.value || null,
  no_faktur: form.no_faktur || null,
  tanggal_faktur: ymd(form.tanggal_faktur),
  catatan: form.catatan || null,
  items: rows.value.map((r) => ({ sparepart_id: r.sparepart_id, satuan_id: r.satuan_id, qty: r.qty, harga: r.harga ?? 0, diskon: r.diskon || 0 })),
})

/** Pemeriksaan ringan di browser; aturan lengkapnya tetap di server. Mengembalikan true bila lolos. */
function periksa() {
  const e = {}
  if (!form.supplier_id && supplierBaru.value.length < 2) e.supplier_id = 'Pilih supplier atau ketik nama supplier baru.'
  if (!form.tanggal_faktur) e.tanggal_faktur = 'Isi tanggal faktur.'
  if (!rows.value.length) e.items = 'Tambahkan minimal satu barang.'
  rows.value.forEach((r, i) => {
    if (!r.satuan_id) e[`items.${i}.satuan_id`] = 'Pilih satuan.'
    if (!(r.qty > 0)) e[`items.${i}.qty`] = 'Isi jumlah.'
    if (r.harga == null) e[`items.${i}.harga`] = 'Isi harga beli.'
  })
  errors.value = e
  if (Object.keys(e).length) toast.add({ severity: 'warn', summary: 'Lengkapi dulu', detail: Object.values(e)[0], life: 4000 })
  return !Object.keys(e).length
}

async function kirim(denganTerima) {
  if (!periksa()) return false
  saving.value = true
  dlgError.value = ''
  try {
    const body = denganTerima ? { ...badan(), terima: { ...terima, jatuh_tempo: terima.status_bayar === 'tempo' ? ymd(terima.jatuh_tempo) : null } } : badan()
    const res = baru.value ? await post('/pembelian', body) : await put(`/pembelian/${id.value}`, body)
    toast.add({ severity: 'success', summary: denganTerima ? 'Barang diterima' : 'Draft tersimpan', detail: res.message, life: 4000 })
    dlg.value.terima = false
    if (baru.value) router.replace(`/pembelian/${res.data.id}`)
    else await muat()
    return true
  } catch (e) {
    errors.value = fieldErrors(e)
    const pesan = Object.values(errors.value)[0] || errMsg(e)
    dlgError.value = pesan
    toast.add({ severity: 'error', summary: 'Belum bisa disimpan', detail: pesan, life: 6000 })
    return false
  } finally {
    saving.value = false
  }
}

function bukaTerima() {
  if (!periksa()) return
  Object.assign(terima, { status_bayar: 'lunas', metode_bayar: 'tunai', sumber: 'laci', jatuh_tempo: new Date(Date.now() + 14 * 86400000) })
  dlgError.value = ''
  dlg.value.terima = true
}

function hapusDraft() {
  confirm.require({
    header: 'Hapus draft ini?',
    message: `${p.value.nomor} akan dihapus. Stok tidak terpengaruh karena belum diterima.`,
    icon: 'pi pi-trash',
    acceptProps: { label: 'Hapus', severity: 'danger' },
    rejectProps: { label: 'Batal', severity: 'secondary', outlined: true },
    accept: async () => {
      try {
        await del(`/pembelian/${id.value}`)
        toast.add({ severity: 'success', summary: 'Draft dihapus', life: 2500 })
        router.push('/pembelian')
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Tidak bisa dihapus', detail: errMsg(e), life: 5000 })
      }
    },
  })
}

// ---------------------------------------------------------------- bayar tempo & batal
async function aksiLain(url, body, tutup) {
  saving.value = true
  dlgError.value = ''
  try {
    const res = await post(url, body)
    p.value = res.data
    toast.add({ severity: 'success', summary: 'Berhasil', detail: res.message, life: 4000 })
    dlg.value[tutup] = false
    await muat()
  } catch (e) {
    dlgError.value = Object.values(fieldErrors(e))[0] || errMsg(e)
  } finally {
    saving.value = false
  }
}
const bukaBayar = () => {
  Object.assign(bayar, { metode_bayar: 'tunai', sumber: 'laci' })
  dlgError.value = ''
  dlg.value.bayar = true
}
const bukaBatal = () => {
  alasan.value = ''
  dlgError.value = ''
  dlg.value.batal = true
}

const METODE = [{ l: 'Tunai', v: 'tunai' }, { l: 'Transfer', v: 'transfer' }]
const SUMBER = [{ l: 'Dari laci kasir', v: 'laci' }, { l: 'Dari luar laci', v: 'luar' }]
const BAYAR = [{ l: 'Lunas sekarang', v: 'lunas' }, { l: 'Tempo (bayar nanti)', v: 'tempo' }]
const e = (k) => errors.value[k]
const sumberTeks = (s) => (s === 'laci' ? 'dari laci kasir' : 'dari luar laci')
</script>

<template>
  <PageHeader
    :title="baru ? 'Terima Barang' : p ? p.nomor : 'Penerimaan Barang'"
    back="/pembelian"
    :subtitle="baru ? 'Catat barang yang datang dari supplier. Stok bertambah saat diterima.' : p ? `Faktur ${tgl(p.tanggal_faktur)} · dicatat ${p.user?.nama}` : ''"
  >
    <Tag v-if="p" :value="STATUS_BELI[p.status].label" :severity="STATUS_BELI[p.status].severity" class="head-tag" />
  </PageHeader>

  <Message v-if="loadError" severity="error" :closable="false">{{ loadError }}</Message>
  <div v-else-if="loading" class="layout"><Skeleton height="420px" border-radius="12px" /><Skeleton height="260px" border-radius="12px" /></div>

  <div v-else class="layout">
    <div class="main">
      <!-- faktur -->
      <section class="panel card">
        <header class="card-head">
          <span class="card-ico"><BIcon name="truk" /></span>
          <div><h2>Data faktur</h2><p class="muted">Isi sesuai faktur atau surat jalan dari supplier.</p></div>
        </header>

        <Message v-if="p?.status === 'batal'" severity="error" :closable="false">
          <strong>Penerimaan dibatalkan</strong> oleh {{ p.batal_user?.nama }} pada {{ tglJam(p.batal_at) }}.<br />Alasan: {{ p.batal_alasan }}
        </Message>

        <div class="grid">
          <div class="field">
            <label for="supplier">Supplier <span v-if="edit" class="req">*</span></label>
            <RemoteSelect v-if="edit" v-model="form.supplier_id" v-model:teks="form.supplier_nama" bebas jenis="supplier" input-id="supplier" :invalid="!!(e('supplier_id') || e('supplier_nama'))" placeholder="Ketik nama supplier" />
            <strong v-else>{{ p.supplier?.nama }}</strong>
            <small v-if="e('supplier_id') || e('supplier_nama')" class="err">{{ e('supplier_id') || e('supplier_nama') }}</small>
            <small v-else-if="edit && supplierBaru" class="hint">Supplier baru <strong>{{ supplierBaru }}</strong> akan dibuat otomatis saat disimpan.</small>
            <small v-else-if="edit" class="hint">Tidak ada di daftar? Ketik saja nama supplier barunya.</small>
          </div>
          <div class="field">
            <label for="faktur">Nomor faktur</label>
            <InputText v-if="edit" id="faktur" v-model="form.no_faktur" maxlength="60" fluid placeholder="Opsional" autocomplete="off" />
            <strong v-else>{{ p.no_faktur || '-' }}</strong>
          </div>
          <div class="field">
            <label for="tgl">Tanggal faktur <span v-if="edit" class="req">*</span></label>
            <DatePicker v-if="edit" v-model="form.tanggal_faktur" input-id="tgl" date-format="dd/mm/yy" :max-date="new Date()" show-icon fluid :invalid="!!e('tanggal_faktur')" />
            <strong v-else>{{ tgl(p.tanggal_faktur) }}</strong>
            <small v-if="e('tanggal_faktur')" class="err">{{ e('tanggal_faktur') }}</small>
          </div>
          <div class="field span2">
            <label for="catatan">Catatan</label>
            <Textarea v-if="edit" id="catatan" v-model="form.catatan" rows="2" auto-resize fluid maxlength="500" placeholder="Opsional, mis. dikirim lewat ekspedisi" />
            <span v-else :class="{ muted: !p.catatan }">{{ p.catatan || '-' }}</span>
          </div>
        </div>
      </section>

      <!-- barang -->
      <section class="panel card">
        <header class="card-head">
          <span class="card-ico"><BIcon name="sparepart" /></span>
          <h2>Barang yang diterima</h2>
          <span class="muted n">{{ rows.length }} jenis</span>
        </header>

        <form v-if="edit" class="cari-baris" @submit.prevent>
          <label for="cari-barang">Tambah barang</label>
          <AutoComplete
            ref="auto"
            v-model="cariInput"
            :suggestions="cariHasil"
            option-label="nama"
            dropdown
            dropdown-mode="blank"
            fluid
            input-id="cari-barang"
            placeholder="Cari nama, kode, atau scan barcode lalu Enter"
            @complete="cari"
            @option-select="pilih"
            @keydown.enter.prevent="enterCari"
          >
            <template #option="{ option }">
              <div class="opt">
                <span>{{ option.nama }} <small v-if="option.merk" class="muted">{{ option.merk }}</small></span>
                <small class="muted">{{ option.kode }} · Stok {{ fmtQty(option.stok) }} {{ option.satuan_dasar }}<template v-if="option.lokasi"> · Rak {{ option.lokasi }}</template></small>
              </div>
            </template>
            <template #empty>
              <div class="tak-ada">
                <span class="muted">Barang tidak ditemukan.</span>
                <Button :label="typeof cariInput === 'string' && cariInput.trim() ? `Tambah “${cariInput.trim()}” sebagai barang baru` : 'Tambah barang baru'" icon="pi pi-plus" size="small" @click="bukaBaru(cariInput)" />
              </div>
            </template>
            <template #footer>
              <div class="kaki-cari">
                <span class="muted">Tidak ada yang cocok?</span>
                <Button label="Tambah barang baru" icon="pi pi-plus" size="small" text @click="bukaBaru(cariInput)" />
              </div>
            </template>
          </AutoComplete>
          <div class="cari-aksi">
            <Button label="Telusuri daftar barang" icon="pi pi-list" size="small" severity="secondary" outlined @click="dlgBarang = true" />
            <Button label="Barang baru" icon="pi pi-plus" size="small" text @click="bukaBaru('')" />
          </div>
        </form>

        <CariBarangDialog v-model:visible="dlgBarang" :dipilih="dipilih" @tambah="(sp) => tambah(sp, null, false)" @baru="bukaBaru" />
        <BarangBaruDialog v-model:visible="dlgBaru" :awal="awalBaru" @dibuat="barangDibuat" />

        <small v-if="e('items')" class="err">{{ e('items') }}</small>

        <div v-if="rows.length" class="tbl" role="table" aria-label="Barang diterima">
          <div class="tr th" role="row">
            <span>Barang</span><span>Satuan</span><span class="r">Jumlah</span><span class="r">Harga beli / satuan</span><span class="r">Diskon</span><span class="r">Subtotal</span><span />
          </div>
          <div v-for="(r, i) in rows" :key="r.k" class="tr" role="row">
            <span class="nm" data-l="Barang">
              {{ r.nama }}
              <small class="muted">{{ r.kode }}<template v-if="r.stok != null"> · stok {{ fmtQty(r.stok) }} {{ r.dasar }}</template></small>
              <small v-if="edit && (r.beliTerakhir || r.hpp)" class="muted">
                <template v-if="r.beliTerakhir">Beli terakhir {{ rp(r.beliTerakhir.harga) }}/{{ r.beliTerakhir.satuan }} ({{ tgl(r.beliTerakhir.tanggal) }})</template>
                <template v-if="r.beliTerakhir && r.hpp"> · </template>
                <template v-if="r.hpp">HPP {{ rp(r.hpp) }}/{{ r.dasar }}</template>
              </small>
              <small v-if="r.qty > 0 && r.isi !== 1" class="masuk">+{{ fmtQty(masuk(r)) }} {{ r.dasar }} masuk stok</small>
            </span>
            <span data-l="Satuan">
              <template v-if="edit">
                <Select v-model="r.satuan_id" :options="r.satuans" option-value="satuan_id" fluid size="small" placeholder="Pilih" :invalid="!!e(`items.${i}.satuan_id`)" aria-label="Satuan" @change="gantiSatuan(r)">
                  <template #value="{ value }">{{ r.satuans.find((s) => s.satuan_id === value)?.nama || 'Pilih' }}</template>
                  <template #option="{ option }">{{ option.nama }}<small class="muted"> · isi {{ fmtQty(option.isi) }}</small></template>
                </Select>
                <small v-if="e(`items.${i}.satuan_id`)" class="err">{{ e(`items.${i}.satuan_id`) }}</small>
              </template>
              <template v-else>{{ r.satuanNama }}<small v-if="r.isi !== 1" class="muted"> (isi {{ fmtQty(r.isi) }})</small></template>
            </span>
            <span class="r" data-l="Jumlah">
              <InputNumber v-if="edit" v-model="r.qty" :min="0" :max-fraction-digits="2" locale="id-ID" fluid size="small" :invalid="!!e(`items.${i}.qty`)" aria-label="Jumlah" />
              <span v-else class="num">{{ fmtQty(r.qty) }}</span>
            </span>
            <span class="r" data-l="Harga beli">
              <InputNumber v-if="edit" v-model="r.harga" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid size="small" :invalid="!!e(`items.${i}.harga`)" aria-label="Harga beli" />
              <span v-else class="num">{{ rp(r.harga) }}</span>
            </span>
            <span class="r" data-l="Diskon">
              <InputNumber v-if="edit" v-model="r.diskon" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid size="small" :invalid="!!e(`items.${i}.diskon`)" aria-label="Diskon" />
              <span v-else class="num">{{ r.diskon ? rp(r.diskon) : '-' }}</span>
            </span>
            <span class="r sub" data-l="Subtotal"><strong class="num">{{ rp(edit ? sub(r) : r.subtotalServer) }}</strong></span>
            <span class="r"><Button v-if="edit" icon="pi pi-trash" text rounded severity="danger" size="small" aria-label="Hapus baris" @click="rows.splice(i, 1)" /></span>
          </div>
        </div>
        <p v-else class="muted kosong-baris">Belum ada barang. Cari di atas untuk menambahkan.</p>
      </section>
    </div>

    <!-- ringkasan -->
    <aside class="side">
      <section class="panel ringkas">
        <h2>Ringkasan</h2>
        <div class="total"><span>Total faktur</span><strong class="num">{{ rp(edit ? total : p.total) }}</strong></div>

        <dl v-if="p?.status === 'diterima' || (p?.status === 'batal' && p.diterima_at)" class="baris">
          <div><dt>Diterima</dt><dd>{{ tglJam(p.diterima_at) }}</dd></div>
          <div>
            <dt>Pembayaran</dt>
            <dd>
              <Tag v-if="p.status_bayar === 'lunas'" value="Lunas" severity="success" />
              <Tag v-else :value="p.terlambat ? 'Terlambat' : 'Tempo'" :severity="p.terlambat ? 'danger' : 'warn'" />
            </dd>
          </div>
          <template v-if="p.status_bayar === 'lunas'">
            <div><dt>Dibayar</dt><dd>{{ p.metode_bayar === 'tunai' ? 'Tunai' : 'Transfer' }}, {{ sumberTeks(p.sumber) }}</dd></div>
            <div><dt>Tanggal bayar</dt><dd>{{ tglJam(p.dibayar_at) }}</dd></div>
          </template>
          <div v-else><dt>Jatuh tempo</dt><dd :class="{ merah: p.terlambat }">{{ tgl(p.jatuh_tempo) }}</dd></div>
        </dl>

        <p v-if="edit && rows.length" class="muted info"><i class="pi pi-info-circle" /> Saat diterima, stok bertambah dan HPP dihitung rata-rata dari stok lama dan harga beli ini.</p>

        <div class="aksi">
          <template v-if="edit">
            <Button label="Terima Barang" icon="pi pi-check" size="large" fluid :disabled="saving" @click="bukaTerima" />
            <Button label="Simpan sebagai Draft" icon="pi pi-save" severity="secondary" outlined fluid :loading="saving" @click="kirim(false)" />
            <Button v-if="!baru" label="Hapus draft" icon="pi pi-trash" text severity="danger" size="small" fluid @click="hapusDraft" />
          </template>
          <template v-else>
            <Button v-if="p.status === 'diterima' && p.status_bayar === 'tempo'" label="Bayar ke Supplier" icon="pi pi-wallet" size="large" fluid @click="bukaBayar" />
            <Button v-if="p.status === 'diterima'" label="Lihat Kartu Stok" icon="pi pi-list" severity="secondary" outlined fluid @click="router.push('/stok')" />
            <Button v-if="p.status === 'diterima' && auth.isOwner" label="Batalkan penerimaan" icon="pi pi-times" text severity="danger" size="small" fluid @click="bukaBatal" />
            <small v-else-if="p.status === 'diterima'" class="muted c">Pembatalan penerimaan hanya bisa dilakukan owner.</small>
          </template>
        </div>
      </section>
    </aside>
  </div>

  <!-- terima -->
  <Dialog v-model:visible="dlg.terima" modal header="Terima Barang" :style="{ width: '30rem' }" :breakpoints="{ '600px': '94vw' }" :closable="!saving">
    <form class="form-dlg" @submit.prevent="kirim(true)">
      <div class="total-dlg">
        <small>Total faktur</small>
        <strong class="num">{{ rp(total) }}</strong>
        <small class="muted">{{ rows.length }} jenis barang akan masuk stok</small>
      </div>

      <div class="field">
        <label>Pembayaran ke supplier</label>
        <SelectButton v-model="terima.status_bayar" :options="BAYAR" option-label="l" option-value="v" :allow-empty="false" fluid />
      </div>

      <template v-if="terima.status_bayar === 'lunas'">
        <div class="field">
          <label>Dibayar dengan</label>
          <SelectButton v-model="terima.metode_bayar" :options="METODE" option-label="l" option-value="v" :allow-empty="false" fluid />
        </div>
        <div class="field">
          <label>Uang diambil</label>
          <SelectButton v-model="terima.sumber" :options="SUMBER" option-label="l" option-value="v" :allow-empty="false" fluid />
          <small class="hint">"Dari laci kasir" akan tercatat sebagai uang keluar di rekap kas.</small>
        </div>
      </template>
      <div v-else class="field">
        <label for="jt">Jatuh tempo <span class="req">*</span></label>
        <DatePicker v-model="terima.jatuh_tempo" input-id="jt" date-format="dd/mm/yy" :min-date="new Date()" show-icon fluid />
        <small class="hint">Pembayaran tercatat sebagai utang dan muncul di dashboard saat mendekati jatuh tempo.</small>
      </div>

      <Message v-if="dlgError" severity="error" :closable="false">{{ dlgError }}</Message>
      <div class="actions">
        <Button type="button" label="Batal" severity="secondary" outlined :disabled="saving" @click="dlg.terima = false" />
        <Button type="submit" label="Terima dan Tambah Stok" icon="pi pi-check" :loading="saving" />
      </div>
    </form>
  </Dialog>

  <!-- bayar tempo -->
  <Dialog v-model:visible="dlg.bayar" modal header="Bayar ke Supplier" :style="{ width: '28rem' }" :breakpoints="{ '600px': '94vw' }" :closable="!saving">
    <form v-if="p" class="form-dlg" @submit.prevent="aksiLain(`/pembelian/${p.id}/bayar`, bayar, 'bayar')">
      <div class="total-dlg"><small>{{ p.supplier?.nama }} · {{ p.nomor }}</small><strong class="num">{{ rp(p.total) }}</strong></div>
      <div class="field">
        <label>Dibayar dengan</label>
        <SelectButton v-model="bayar.metode_bayar" :options="METODE" option-label="l" option-value="v" :allow-empty="false" fluid />
      </div>
      <div class="field">
        <label>Uang diambil</label>
        <SelectButton v-model="bayar.sumber" :options="SUMBER" option-label="l" option-value="v" :allow-empty="false" fluid />
      </div>
      <Message v-if="dlgError" severity="error" :closable="false">{{ dlgError }}</Message>
      <div class="actions">
        <Button type="button" label="Batal" severity="secondary" outlined :disabled="saving" @click="dlg.bayar = false" />
        <Button type="submit" label="Tandai Lunas" icon="pi pi-check" :loading="saving" />
      </div>
    </form>
  </Dialog>

  <!-- batal -->
  <Dialog v-model:visible="dlg.batal" modal header="Batalkan Penerimaan" :style="{ width: '30rem' }" :breakpoints="{ '600px': '94vw' }" :closable="!saving">
    <form v-if="p" class="form-dlg" @submit.prevent="aksiLain(`/pembelian/${p.id}/batal`, { alasan }, 'batal')">
      <Message severity="warn" :closable="false">
        <strong>{{ p.nomor }}</strong> dibatalkan dan stok barangnya dikurangi lagi. Hanya bisa bila barangnya belum terjual atau terpakai. HPP tidak dihitung mundur.
      </Message>
      <div class="field">
        <label for="alasan">Alasan pembatalan <span class="req">*</span></label>
        <Textarea id="alasan" v-model="alasan" rows="3" auto-resize fluid placeholder="Mis. faktur salah input, barang dikembalikan ke supplier" autofocus />
      </div>
      <Message v-if="dlgError" severity="error" :closable="false">{{ dlgError }}</Message>
      <div class="actions">
        <Button type="button" label="Kembali" severity="secondary" outlined :disabled="saving" @click="dlg.batal = false" />
        <Button type="submit" label="Batalkan Penerimaan" icon="pi pi-times" severity="danger" :loading="saving" :disabled="alasan.trim().length < 3" />
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
.card-head p {
  margin: 2px 0 0;
  font-size: 0.9rem;
}
.card-head .n {
  margin-left: auto;
}
.card-ico {
  width: 40px;
  height: 40px;
  flex: none;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
  color: var(--accent-text);
}
.head-tag {
  font-size: 0.95rem;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}
.span2 {
  grid-column: 1 / -1;
}
.link {
  color: var(--accent-text);
  text-decoration: underline;
}
.cari-baris {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px dashed var(--p-content-border-color);
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-primary-color) 3%, transparent);
}
.cari-baris label {
  font-weight: 600;
  font-size: 0.9rem;
}
.opt {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}
.cari-aksi {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.tak-ada,
.kaki-cari {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 12px;
}
.kaki-cari {
  border-top: 1px solid var(--p-content-border-color);
}

.tbl {
  display: flex;
  flex-direction: column;
}
.tr {
  display: grid;
  grid-template-columns: minmax(120px, 1.8fr) minmax(90px, 1fr) minmax(70px, 0.7fr) minmax(110px, 1.2fr) minmax(90px, 0.9fr) minmax(90px, 1fr) 32px;
  gap: 8px;
  align-items: start;
  padding: 12px 0;
  border-bottom: 1px solid var(--p-content-border-color);
}
.th {
  padding: 0 0 8px;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
  align-items: end;
}
.r {
  text-align: right;
}
.sub {
  padding-top: 8px;
}
.nm {
  display: flex;
  flex-direction: column;
  line-height: 1.35;
  font-weight: 500;
  padding-top: 4px;
}
.nm small {
  font-weight: 400;
}
.masuk {
  color: var(--p-green-600);
  font-weight: 600 !important;
}
.kosong-baris {
  margin: 0;
  padding: 8px 0;
}

.ringkas {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.ringkas h2 {
  font-size: 1.1rem;
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
.merah {
  color: var(--p-red-500);
  font-weight: 700;
}
.info {
  margin: 0;
  font-size: 0.88rem;
}
.aksi {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.c {
  text-align: center;
}

.form-dlg {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.total-dlg {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 14px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--p-primary-color) 8%, transparent);
}
.total-dlg small {
  color: var(--p-text-muted-color);
}
.total-dlg strong {
  font-size: 1.9rem;
  color: var(--accent-text);
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

@media (max-width: 1100px) {
  .layout {
    grid-template-columns: 1fr;
  }
  .side {
    position: static;
  }
}
@media (max-width: 860px) {
  .th {
    display: none;
  }
  .tr {
    grid-template-columns: 1fr 1fr;
  }
  .tr .nm {
    grid-column: 1 / -1;
  }
  .tr .r {
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
}
</style>
