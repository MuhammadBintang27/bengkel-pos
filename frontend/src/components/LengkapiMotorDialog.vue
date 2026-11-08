<script setup>
import { reactive, ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import { errMsg, fieldErrors, post, put } from '@/api/http'
import RemoteSelect from '@/components/RemoteSelect.vue'

// Lengkapi data motor yang awalnya hanya dicatat platnya: merk, tipe, dan pemilik.
const props = defineProps({ kendaraan: Object })
const visible = defineModel('visible', { default: false })
const emit = defineEmits(['saved'])

const toast = useToast()
const pemilik = ref('baru')
const form = reactive({ merk: '', tipe: '', tahun: null, warna: '', pelanggan_id: null, nama: '', telepon: '' })
const errors = ref({})
const saving = ref(false)
let pelangganBaruId = null // pemilik yang sudah terbuat bila simpan motor gagal, agar tidak dobel saat dicoba lagi

const MERK = ['Honda', 'Yamaha', 'Suzuki', 'Kawasaki', 'TVS', 'Vespa']

watch(visible, (v) => {
  if (!v || !props.kendaraan) return
  const k = props.kendaraan
  Object.assign(form, { merk: k.merk || '', tipe: k.tipe || '', tahun: k.tahun ?? null, warna: k.warna || '', pelanggan_id: k.pelanggan_id ?? null, nama: '', telepon: '' })
  pemilik.value = k.pelanggan_id ? 'lama' : 'baru'
  errors.value = {}
  pelangganBaruId = null
})

const e = (k) => errors.value[k]

async function simpan() {
  saving.value = true
  errors.value = {}
  try {
    let pelangganId = pemilik.value === 'lama' ? form.pelanggan_id : null
    if (pemilik.value === 'baru' && form.nama.trim()) {
      pelangganId = pelangganBaruId ??= (await post('/master/pelanggan', { nama: form.nama.trim(), telepon: form.telepon || null })).data.id
    } else if (pemilik.value === 'baru') {
      pelangganId = props.kendaraan.pelanggan_id ?? null // nama dikosongkan: pemilik tidak diubah
    }
    await put(`/master/kendaraan/${props.kendaraan.id}`, {
      plat: props.kendaraan.plat, pelanggan_id: pelangganId, merk: form.merk || null, tipe: form.tipe || null, tahun: form.tahun, warna: form.warna || null,
    })
    toast.add({ severity: 'success', summary: 'Data motor disimpan', life: 2500 })
    visible.value = false
    emit('saved')
  } catch (err) {
    errors.value = fieldErrors(err)
    if (!Object.keys(errors.value).length) toast.add({ severity: 'error', summary: 'Gagal menyimpan', detail: errMsg(err), life: 5000 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal header="Lengkapi Data Motor" :style="{ width: '36rem' }" :breakpoints="{ '700px': '94vw' }" :closable="!saving">
    <form v-if="kendaraan" class="form" @submit.prevent="simpan">
      <div class="full plat-info"><strong class="plat">{{ kendaraan.plat }}</strong><span class="muted">Semua isian di bawah boleh dikosongkan.</span></div>

      <div class="field">
        <label for="lm-merk">Merk</label>
        <InputText id="lm-merk" v-model="form.merk" fluid list="lm-merk-list" :invalid="!!e('merk')" autocomplete="off" />
        <datalist id="lm-merk-list"><option v-for="m in MERK" :key="m" :value="m" /></datalist>
      </div>
      <div class="field">
        <label for="lm-tipe">Tipe</label>
        <InputText id="lm-tipe" v-model="form.tipe" fluid placeholder="Vario 125, Beat, NMAX" :invalid="!!e('tipe')" autocomplete="off" />
      </div>
      <div class="field">
        <label for="lm-thn">Tahun</label>
        <InputNumber v-model="form.tahun" input-id="lm-thn" :use-grouping="false" :max-fraction-digits="0" fluid :invalid="!!e('tahun')" />
        <small v-if="e('tahun')" class="err">{{ e('tahun') }}</small>
      </div>
      <div class="field">
        <label for="lm-warna">Warna</label>
        <InputText id="lm-warna" v-model="form.warna" fluid autocomplete="off" />
      </div>

      <div class="field full">
        <label>Pemilik</label>
        <SelectButton v-model="pemilik" :options="[{ l: 'Pelanggan lama', v: 'lama' }, { l: 'Pelanggan baru', v: 'baru' }]" option-label="l" option-value="v" :allow-empty="false" />
      </div>
      <div v-if="pemilik === 'lama'" class="field full">
        <label for="lm-pel">Cari pelanggan</label>
        <RemoteSelect v-model="form.pelanggan_id" jenis="pelanggan" input-id="lm-pel" placeholder="Ketik nama atau telepon" />
      </div>
      <template v-else>
        <div class="field">
          <label for="lm-nama">Nama pemilik</label>
          <InputText id="lm-nama" v-model="form.nama" fluid :invalid="!!e('nama')" autocomplete="off" />
          <small v-if="e('nama')" class="err">{{ e('nama') }}</small>
        </div>
        <div class="field">
          <label for="lm-telp">Telepon / WhatsApp</label>
          <InputText id="lm-telp" v-model="form.telepon" fluid inputmode="tel" autocomplete="off" />
        </div>
      </template>

      <div class="actions">
        <Button type="button" label="Batal" severity="secondary" outlined :disabled="saving" @click="visible = false" />
        <Button type="submit" label="Simpan" icon="pi pi-check" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>

<style scoped>
.form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.full {
  grid-column: 1 / -1;
}
.plat-info {
  display: flex;
  align-items: center;
  gap: 12px;
}
.plat {
  padding: 4px 12px;
  border: 2px solid var(--p-text-color);
  border-radius: 6px;
  letter-spacing: 0.08em;
}
.actions {
  grid-column: 1 / -1;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
@media (max-width: 560px) {
  .form {
    grid-template-columns: 1fr;
  }
}
</style>
