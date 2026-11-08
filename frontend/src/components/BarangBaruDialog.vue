<script setup>
import { reactive, ref, watch } from 'vue'
import { errMsg, fieldErrors, get, post } from '@/api/http'
import RemoteSelect from '@/components/RemoteSelect.vue'

// Tambah barang baru dari layar penerimaan: isian seperlunya. Stok masuk lewat penerimaan itu sendiri, jadi tanpa stok awal.
const visible = defineModel('visible', { type: Boolean, default: false })
const props = defineProps({ awal: { type: Object, default: () => ({}) } })
const emit = defineEmits(['dibuat'])

const kosong = () => ({ nama: '', kategori_id: null, satuan_dasar_id: null, harga_jual: null, barcode: '', besar: false, satuan_besar_id: null, isi: null, harga_besar: null })
const form = reactive(kosong())
const errors = ref({})
const saving = ref(false)
const galat = ref('')

watch(visible, (v) => {
  if (!v) return
  Object.assign(form, kosong(), { nama: props.awal.nama || '', barcode: props.awal.barcode || '' })
  errors.value = {}
  galat.value = ''
})

const e = (k) => errors.value[k]

function periksa() {
  const x = {}
  if (!form.nama.trim()) x.nama = 'Isi nama barang.'
  if (!form.kategori_id) x.kategori_id = 'Pilih kategori.'
  if (!form.satuan_dasar_id) x.satuan_dasar_id = 'Pilih satuan.'
  if (form.harga_jual == null) x['satuans.0.harga_jual'] = 'Isi harga jual.'
  if (form.besar) {
    if (!form.satuan_besar_id) x['satuans.1.satuan_id'] = 'Pilih satuan besar.'
    if (!(form.isi > 1)) x['satuans.1.isi'] = 'Isi harus lebih dari 1.'
    if (form.harga_besar == null) x['satuans.1.harga_jual'] = 'Isi harga jual.'
  }
  errors.value = x
  return !Object.keys(x).length
}

async function simpan() {
  if (!periksa()) return
  saving.value = true
  galat.value = ''
  const satuans = [{ satuan_id: form.satuan_dasar_id, isi: 1, harga_jual: form.harga_jual, barcode: form.barcode.trim() || null }]
  if (form.besar) satuans.push({ satuan_id: form.satuan_besar_id, isi: form.isi, harga_jual: form.harga_besar, barcode: null })
  try {
    const baru = (await post('/sparepart-cepat', { nama: form.nama.trim(), kategori_id: form.kategori_id, satuan_dasar_id: form.satuan_dasar_id, stok_min: 0, aktif: true, satuans })).data
    // bentuk data yang sama dengan hasil pencarian, supaya bisa langsung masuk baris penerimaan
    emit('dibuat', (await get('/cari/sparepart', { ids: baru.id })).data[0])
    visible.value = false
  } catch (err) {
    errors.value = fieldErrors(err)
    galat.value = Object.values(errors.value)[0] || errMsg(err)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal header="Tambah Barang Baru" :style="{ width: '34rem' }" :breakpoints="{ '600px': '94vw' }" :closable="!saving">
    <form class="form" @submit.prevent="simpan">
      <p class="muted info">Isi data pokoknya saja. Stok bertambah saat barang diterima, dan kode dibuat otomatis. Detail lain bisa dilengkapi nanti oleh owner di Master Sparepart.</p>

      <div class="field">
        <label for="bb-nama">Nama barang <span class="req">*</span></label>
        <InputText id="bb-nama" v-model="form.nama" maxlength="150" fluid autofocus :invalid="!!e('nama')" />
        <small v-if="e('nama')" class="err">{{ e('nama') }}</small>
      </div>

      <div class="field">
        <label for="bb-kat">Kategori <span class="req">*</span></label>
        <RemoteSelect v-model="form.kategori_id" jenis="kategori-sparepart" input-id="bb-kat" :invalid="!!e('kategori_id')" placeholder="Ketik untuk mencari kategori" />
        <small v-if="e('kategori_id')" class="err">{{ e('kategori_id') }}</small>
      </div>

      <div class="baris">
        <div class="field">
          <label for="bb-sat">Satuan terkecil <span class="req">*</span></label>
          <RemoteSelect v-model="form.satuan_dasar_id" jenis="satuan" input-id="bb-sat" :invalid="!!(e('satuan_dasar_id') || e('satuans'))" placeholder="mis. pcs, liter" />
          <small v-if="e('satuan_dasar_id') || e('satuans')" class="err">{{ e('satuan_dasar_id') || e('satuans') }}</small>
        </div>
        <div class="field">
          <label for="bb-harga">Harga jual / satuan <span class="req">*</span></label>
          <InputNumber v-model="form.harga_jual" input-id="bb-harga" prefix="Rp" :min="0" fluid :invalid="!!e('satuans.0.harga_jual')" />
          <small v-if="e('satuans.0.harga_jual')" class="err">{{ e('satuans.0.harga_jual') }}</small>
        </div>
      </div>

      <div class="field">
        <label for="bb-bc">Barcode <span class="muted">(opsional)</span></label>
        <InputText id="bb-bc" v-model="form.barcode" maxlength="60" fluid :invalid="!!e('satuans.0.barcode')" />
        <small v-if="e('satuans.0.barcode')" class="err">{{ e('satuans.0.barcode') }}</small>
      </div>

      <div class="besar">
        <label class="cek"><Checkbox v-model="form.besar" binary input-id="bb-besar" /><span>Barang ini juga dibeli dalam satuan lebih besar (box, dus, lusin)</span></label>
        <div v-if="form.besar" class="baris tiga">
          <div class="field">
            <label for="bb-sb">Satuan besar</label>
            <RemoteSelect v-model="form.satuan_besar_id" jenis="satuan" input-id="bb-sb" :invalid="!!e('satuans.1.satuan_id')" placeholder="mis. box" />
            <small v-if="e('satuans.1.satuan_id')" class="err">{{ e('satuans.1.satuan_id') }}</small>
          </div>
          <div class="field">
            <label for="bb-isi">Isi (jumlah satuan kecil)</label>
            <InputNumber v-model="form.isi" input-id="bb-isi" :min="2" :max-fraction-digits="2" fluid :invalid="!!e('satuans.1.isi')" />
            <small v-if="e('satuans.1.isi')" class="err">{{ e('satuans.1.isi') }}</small>
          </div>
          <div class="field">
            <label for="bb-hb">Harga jual / satuan besar</label>
            <InputNumber v-model="form.harga_besar" input-id="bb-hb" prefix="Rp" :min="0" fluid :invalid="!!e('satuans.1.harga_jual')" />
            <small v-if="e('satuans.1.harga_jual')" class="err">{{ e('satuans.1.harga_jual') }}</small>
          </div>
        </div>
      </div>

      <Message v-if="galat" severity="error" :closable="false">{{ galat }}</Message>

      <div class="aksi">
        <Button type="button" label="Batal" severity="secondary" outlined :disabled="saving" @click="visible = false" />
        <Button type="submit" label="Simpan dan Tambahkan" icon="pi pi-check" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>

<style scoped>
.form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.info {
  margin: 0;
  font-size: 0.9rem;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}
.baris {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.baris.tiga {
  grid-template-columns: 1fr;
}
.besar {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-text-muted-color) 8%, transparent);
}
.cek {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}
.aksi {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
@media (max-width: 600px) {
  .baris {
    grid-template-columns: 1fr;
  }
}
</style>
