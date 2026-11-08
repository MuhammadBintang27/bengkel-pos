<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { errMsg, fieldErrors, get, post, put } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import CardSkeleton from '@/components/CardSkeleton.vue'
import PageHeader from '@/components/PageHeader.vue'
import { qty, rp } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()

const id = computed(() => route.params.id)
const baru = computed(() => !id.value)

const loading = ref(true)
const loadError = ref('')
const saving = ref(false)
const errors = ref({})
const kategori = ref([])
const raks = ref([])
const satuans = ref([])
const info = ref(null) // sparepart yang sedang diubah (stok & HPP saat ini)

const form = reactive({
  kode: '', part_number: '', nama: '', kategori_id: null, merk: '', kompatibel: '', rak_id: null,
  satuan_dasar_id: null, stok_min: 0, aktif: true, stok_awal: null, hpp: null,
})
// Baris satuan dasar (isi selalu 1) dipisah dari satuan lain supaya tidak bisa terhapus atau berubah isinya.
const dasar = reactive({ harga_jual: null, barcode: '' })
const lain = ref([])
let awal = ''

const snapshot = () => JSON.stringify([form, dasar, lain.value])
const dirty = computed(() => !loading.value && snapshot() !== awal)

const namaSatuan = (sid) => satuans.value.find((s) => s.id === sid)?.nama
const dasarNama = computed(() => namaSatuan(form.satuan_dasar_id) || 'satuan dasar')
const pilihanLain = (row) => satuans.value.filter((s) => s.id !== form.satuan_dasar_id && (s.id === row.satuan_id || !lain.value.some((l) => l.satuan_id === s.id)))

const e = (k) => errors.value[k]
const eLain = (i, f) => errors.value[`satuans.${i + 1}.${f}`]

function tambahSatuan() {
  const dipakai = new Set([form.satuan_dasar_id, ...lain.value.map((l) => l.satuan_id)])
  lain.value.push({ satuan_id: satuans.value.find((s) => !dipakai.has(s.id))?.id ?? null, isi: null, harga_jual: null, barcode: '', aktif: true })
}

// Harga satuan lain dibandingkan dengan harga eceran: bantu owner melihat apakah diskon box masuk akal.
function perDasar(row) {
  if (!row.isi || row.harga_jual == null) return null
  const per = Math.round(row.harga_jual / row.isi)
  const hemat = dasar.harga_jual ? Math.round((1 - per / dasar.harga_jual) * 100) : null
  return { per, hemat }
}

onMounted(async () => {
  try {
    const [k, s, r] = await Promise.all([get('/master/kategori-sparepart', { per_page: 100 }), get('/master/satuan', { per_page: 100 }), get('/master/rak', { per_page: 100 })])
    kategori.value = k.data
    raks.value = r.data
    satuans.value = s.data
    if (!baru.value) {
      const sp = (await get(`/sparepart/${id.value}`)).data
      info.value = sp
      Object.assign(form, Object.fromEntries(Object.keys(form).filter((k) => k in sp).map((k) => [k, sp[k]])))
      form.stok_min = Number(sp.stok_min)
      const base = sp.satuans.find((x) => x.satuan_id === sp.satuan_dasar_id)
      Object.assign(dasar, { harga_jual: base?.harga_jual ?? null, barcode: base?.barcode ?? '' })
      lain.value = sp.satuans
        .filter((x) => x.satuan_id !== sp.satuan_dasar_id)
        .map((x) => ({ satuan_id: x.satuan_id, isi: Number(x.isi), harga_jual: x.harga_jual, barcode: x.barcode ?? '', aktif: x.aktif }))
    } else {
      form.satuan_dasar_id = satuans.value.find((x) => x.nama === 'pcs')?.id ?? null
    }
    awal = snapshot()
  } catch (err) {
    loadError.value = err.response?.status === 404 ? 'Sparepart tidak ditemukan. Mungkin sudah dihapus.' : errMsg(err)
  } finally {
    loading.value = false
  }
})

async function simpan() {
  saving.value = true
  errors.value = {}
  const body = {
    ...form,
    kode: form.kode || null,
    satuans: [
      { satuan_id: form.satuan_dasar_id, isi: 1, harga_jual: dasar.harga_jual, barcode: dasar.barcode || null, aktif: true },
      ...lain.value.map((l) => ({ ...l, barcode: l.barcode || null })),
    ],
  }
  if (!baru.value) {
    delete body.stok_awal
    delete body.hpp
  }
  try {
    const res = baru.value ? await post('/sparepart', body) : await put(`/sparepart/${id.value}`, body)
    awal = snapshot() // agar penjaga "belum disimpan" tidak muncul
    toast.add({ severity: 'success', summary: 'Berhasil', detail: res.message, life: 3000 })
    router.push('/sparepart')
  } catch (err) {
    errors.value = fieldErrors(err)
    const first = Object.values(errors.value)[0]
    toast.add({ severity: 'error', summary: 'Belum bisa disimpan', detail: first || errMsg(err), life: 6000 })
    if (first) document.querySelector('.err')?.scrollIntoView({ block: 'center', behavior: 'smooth' })
  } finally {
    saving.value = false
  }
}

onBeforeRouteLeave(() => {
  if (!dirty.value || saving.value) return true
  return new Promise((resolve) =>
    confirm.require({
      header: 'Perubahan belum disimpan',
      message: 'Tinggalkan halaman dan buang perubahan?',
      icon: 'pi pi-exclamation-triangle',
      acceptProps: { label: 'Buang perubahan', severity: 'danger' },
      rejectProps: { label: 'Tetap di sini', severity: 'secondary', outlined: true },
      accept: () => resolve(true),
      reject: () => resolve(false),
      onHide: () => resolve(false),
    }),
  )
})
</script>

<template>
  <PageHeader :title="baru ? 'Tambah Sparepart' : 'Ubah Sparepart'" back="/sparepart" :subtitle="info ? `${info.kode} · ${info.nama}` : 'Isi data barang, satuan, dan harga jualnya.'" />

  <Message v-if="loadError" severity="error" :closable="false">{{ loadError }}</Message>
  <CardSkeleton v-else-if="loading" :cards="3" :fields="4" />

  <form v-else class="stack-form" @submit.prevent="simpan">
    <!-- Data barang -->
    <section class="panel card">
      <header class="card-head">
        <span class="card-ico"><BIcon name="sparepart" /></span>
        <div><h2>Data barang</h2><p class="muted">Nama dan keterangan yang membantu kasir menemukan barang.</p></div>
      </header>
      <div class="grid">
        <div class="field span2">
          <label for="nama">Nama barang <span class="req">*</span></label>
          <InputText id="nama" v-model="form.nama" maxlength="150" fluid :invalid="!!e('nama')" placeholder="Contoh: Busi NGK C7HSA" autofocus />
          <small v-if="e('nama')" class="err">{{ e('nama') }}</small>
        </div>
        <div class="field">
          <label for="kategori">Kategori <span class="req">*</span></label>
          <Select id="kategori" v-model="form.kategori_id" :options="kategori" option-label="nama" option-value="id" filter placeholder="Pilih kategori" fluid :invalid="!!e('kategori_id')" />
          <small v-if="e('kategori_id')" class="err">{{ e('kategori_id') }}</small>
          <small v-else class="hint">Belum ada yang cocok? <RouterLink to="/master/kategori-sparepart" class="link">Kelola kategori</RouterLink></small>
        </div>
        <div class="field">
          <label for="merk">Merk</label>
          <InputText id="merk" v-model="form.merk" maxlength="60" fluid placeholder="NGK, Shell, Federal..." />
        </div>
        <div class="field">
          <label for="kode">Kode</label>
          <InputText id="kode" v-model="form.kode" maxlength="40" fluid :invalid="!!e('kode')" placeholder="Kosongkan untuk dibuat otomatis" />
          <small v-if="e('kode')" class="err">{{ e('kode') }}</small>
        </div>
        <div class="field">
          <label for="pn">Part number</label>
          <InputText id="pn" v-model="form.part_number" maxlength="60" fluid placeholder="Nomor dari pabrik" />
        </div>
        <div class="field span2">
          <label for="kompat">Cocok untuk motor</label>
          <InputText id="kompat" v-model="form.kompatibel" maxlength="255" fluid placeholder="Beat, Vario, Scoopy" />
          <small class="hint">Ikut dicari saat kasir mengetik tipe motor.</small>
        </div>
        <div class="field">
          <label for="rak">Rak</label>
          <Select id="rak" v-model="form.rak_id" :options="raks" option-label="nama" option-value="id" filter show-clear placeholder="Pilih rak" fluid />
          <small class="hint">Tampil di kasir supaya mudah mengambil barang. <RouterLink to="/master/rak" class="link">Kelola rak</RouterLink></small>
        </div>
        <div class="field">
          <label>Status</label>
          <div class="sw"><ToggleSwitch v-model="form.aktif" /> <span>{{ form.aktif ? 'Aktif, bisa dijual' : 'Nonaktif, tidak muncul di kasir' }}</span></div>
        </div>
      </div>
    </section>

    <!-- Satuan & harga -->
    <section class="panel card">
      <header class="card-head">
        <span class="card-ico"><BIcon name="stok" /></span>
        <div><h2>Satuan dan harga jual</h2><p class="muted">Stok dihitung dalam satuan dasar. Satuan lain (box, dus) boleh punya harga sendiri.</p></div>
      </header>

      <div class="field narrow">
        <label for="dasar">Satuan dasar (satuan terkecil) <span class="req">*</span></label>
        <Select id="dasar" v-model="form.satuan_dasar_id" :options="satuans" option-label="nama" option-value="id" filter placeholder="Pilih satuan" fluid :invalid="!!e('satuan_dasar_id')" />
        <small v-if="e('satuan_dasar_id')" class="err">{{ e('satuan_dasar_id') }}</small>
        <small v-else class="hint">Contoh: oli dalam liter, busi dalam pcs. Tidak bisa diubah setelah ada riwayat stok.</small>
      </div>

      <div class="sat-head"><span>Satuan</span><span>Isi</span><span>Harga jual</span><span>Barcode</span><span /></div>

      <!-- satuan dasar -->
      <div class="sat-row dasar-row">
        <div class="sat-name"><strong>{{ dasarNama }}</strong><Tag value="Dasar" severity="info" /></div>
        <div class="isi-fix muted" data-label="Isi">1 {{ dasarNama }}</div>
        <div class="field" data-label="Harga jual">
          <InputNumber v-model="dasar.harga_jual" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid :invalid="!!e('satuans.0.harga_jual')" :aria-label="`Harga jual per ${dasarNama}`" />
          <small v-if="e('satuans.0.harga_jual')" class="err">{{ e('satuans.0.harga_jual') }}</small>
        </div>
        <div class="field" data-label="Barcode">
          <InputText v-model="dasar.barcode" maxlength="60" fluid :invalid="!!e('satuans.0.barcode')" placeholder="Scan atau ketik" aria-label="Barcode satuan dasar" />
          <small v-if="e('satuans.0.barcode')" class="err">{{ e('satuans.0.barcode') }}</small>
        </div>
        <span />
      </div>

      <!-- satuan lain -->
      <div v-for="(row, i) in lain" :key="i" class="sat-row">
        <div class="field" data-label="Satuan">
          <Select v-model="row.satuan_id" :options="pilihanLain(row)" option-label="nama" option-value="id" placeholder="Pilih" fluid :invalid="!!eLain(i, 'satuan_id')" aria-label="Satuan" />
          <small v-if="eLain(i, 'satuan_id')" class="err">{{ eLain(i, 'satuan_id') }}</small>
        </div>
        <div class="field" data-label="Isi">
          <InputNumber v-model="row.isi" :min="0" :max-fraction-digits="2" locale="id-ID" fluid :invalid="!!eLain(i, 'isi')" :suffix="` ${dasarNama}`" placeholder="10" aria-label="Isi dalam satuan dasar" />
          <small v-if="eLain(i, 'isi')" class="err">{{ eLain(i, 'isi') }}</small>
        </div>
        <div class="field" data-label="Harga jual">
          <InputNumber v-model="row.harga_jual" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid :invalid="!!eLain(i, 'harga_jual')" aria-label="Harga jual" />
          <small v-if="eLain(i, 'harga_jual')" class="err">{{ eLain(i, 'harga_jual') }}</small>
          <small v-else-if="perDasar(row)" class="hint">
            = {{ rp(perDasar(row).per) }} per {{ dasarNama }}<template v-if="perDasar(row).hemat > 0"> (lebih murah {{ perDasar(row).hemat }}%)</template><template v-else-if="perDasar(row).hemat < 0"> (lebih mahal dari eceran)</template>
          </small>
        </div>
        <div class="field" data-label="Barcode">
          <InputText v-model="row.barcode" maxlength="60" fluid :invalid="!!eLain(i, 'barcode')" placeholder="Opsional" aria-label="Barcode" />
          <small v-if="eLain(i, 'barcode')" class="err">{{ eLain(i, 'barcode') }}</small>
        </div>
        <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus satuan" @click="lain.splice(i, 1)" />
      </div>

      <small v-if="e('satuans')" class="err">{{ e('satuans') }}</small>
      <Button label="Tambah satuan lain" icon="pi pi-plus" text size="small" class="self-start" :disabled="lain.length >= 9" @click="tambahSatuan" />
    </section>

    <!-- Stok -->
    <section class="panel card">
      <header class="card-head">
        <span class="card-ico"><BIcon name="truk" /></span>
        <div><h2>Stok</h2><p class="muted">{{ baru ? 'Isi stok awal bila barang sudah ada di rak.' : 'Stok berubah lewat penerimaan barang, penjualan, atau penyesuaian.' }}</p></div>
      </header>

      <div class="grid">
        <div class="field">
          <label for="min">Stok minimum</label>
          <InputNumber v-model="form.stok_min" input-id="min" :min="0" :max-fraction-digits="2" locale="id-ID" :suffix="` ${dasarNama}`" fluid :invalid="!!e('stok_min')" />
          <small v-if="e('stok_min')" class="err">{{ e('stok_min') }}</small>
          <small v-else class="hint">Muncul peringatan "menipis" bila stok sama atau di bawah ini. Isi 0 untuk tidak memantau.</small>
        </div>

        <template v-if="baru">
          <div class="field">
            <label for="awal">Stok awal</label>
            <InputNumber v-model="form.stok_awal" input-id="awal" :min="0" :max-fraction-digits="2" locale="id-ID" :suffix="` ${dasarNama}`" fluid :invalid="!!e('stok_awal')" placeholder="0" />
            <small v-if="e('stok_awal')" class="err">{{ e('stok_awal') }}</small>
          </div>
          <div v-if="form.stok_awal > 0" class="field">
            <label for="hpp">Harga beli per {{ dasarNama }} <span class="req">*</span></label>
            <InputNumber v-model="form.hpp" input-id="hpp" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid :invalid="!!e('hpp')" />
            <small v-if="e('hpp')" class="err">{{ e('hpp') }}</small>
            <small v-else class="hint">Dasar menghitung laba kotor. Setelah ini HPP dihitung rata-rata dari penerimaan barang.</small>
          </div>
        </template>
        <div v-else class="stok-info">
          <div><small class="muted">Stok saat ini</small><strong class="num">{{ qty(info.stok) }} {{ info.satuan_dasar?.nama }}</strong></div>
          <div><small class="muted">HPP rata-rata</small><strong class="num">{{ rp(info.hpp) }} / {{ info.satuan_dasar?.nama }}</strong></div>
        </div>
      </div>
    </section>

    <div class="bar">
      <Button type="button" label="Batal" severity="secondary" outlined :disabled="saving" @click="router.push('/sparepart')" />
      <Button type="submit" :label="baru ? 'Simpan Sparepart' : 'Simpan Perubahan'" icon="pi pi-check" :loading="saving" />
    </div>
  </form>
</template>

<style scoped>
.stack-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-bottom: 24px;
}
.card {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 18px;
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
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}
.span2 {
  grid-column: span 2;
}
.narrow {
  max-width: 340px;
}
.sw {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
}
.link {
  color: var(--accent-text);
  text-decoration: underline;
}

.sat-head,
.sat-row {
  display: grid;
  grid-template-columns: 1.1fr 1fr 1.3fr 1.2fr 44px;
  gap: 12px;
  align-items: start;
}
.sat-head {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
  padding: 0 2px;
}
.sat-row {
  padding: 12px;
  border: 1px solid var(--p-content-border-color);
  border-radius: 10px;
}
.dasar-row {
  background: color-mix(in srgb, var(--p-primary-color) 5%, transparent);
}
.sat-name {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 40px;
}
.isi-fix {
  min-height: 40px;
  display: flex;
  align-items: center;
}
.stok-info {
  display: flex;
  gap: 32px;
  align-items: center;
  flex-wrap: wrap;
}
.stok-info div {
  display: flex;
  flex-direction: column;
}
.stok-info strong {
  font-size: 1.2rem;
}
.self-start {
  align-self: flex-start;
}

.bar {
  position: sticky;
  bottom: 0;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 0;
  background: linear-gradient(to top, var(--app-bg) 70%, transparent);
}

@media (max-width: 760px) {
  .span2 {
    grid-column: auto;
  }
  .sat-head {
    display: none;
  }
  .sat-row {
    grid-template-columns: 1fr 1fr;
  }
  .sat-row > [data-label]::before {
    content: attr(data-label);
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--p-text-muted-color);
    margin-bottom: 4px;
  }
  .isi-fix {
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
    min-height: 0;
  }
  .sat-row > :nth-child(5) {
    grid-column: 1 / -1;
    justify-self: end;
  }
}
</style>
