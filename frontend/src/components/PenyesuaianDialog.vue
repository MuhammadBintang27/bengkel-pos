<script setup>
import { computed, ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import { errMsg, fieldErrors, post } from '@/api/http'
import { qty as fmtQty } from '@/utils/format'

// Penyesuaian stok hasil hitung fisik (stok opname). Hanya owner; selisihnya tercatat di kartu stok.
const props = defineProps({ sparepart: Object })
const visible = defineModel('visible', { default: false })
const emit = defineEmits(['saved'])

const toast = useToast()
const stokBaru = ref(null)
const ket = ref('')
const errors = ref({})
const saving = ref(false)

const satuan = computed(() => props.sparepart?.satuan_dasar?.nama || '')
const selisih = computed(() => (stokBaru.value == null ? null : Math.round((stokBaru.value - props.sparepart.stok) * 100) / 100))
const hppBaru = ref(null)
const hppBerubah = computed(() => hppBaru.value != null && hppBaru.value !== Math.round(props.sparepart.hpp))
const adaPerubahan = computed(() => selisih.value !== 0 || hppBerubah.value)

watch(visible, (v) => {
  if (!v || !props.sparepart) return
  stokBaru.value = Number(props.sparepart.stok)
  hppBaru.value = Math.round(props.sparepart.hpp)
  ket.value = ''
  errors.value = {}
})

async function simpan() {
  saving.value = true
  errors.value = {}
  try {
    // Stok dan HPP bisa dikoreksi sekaligus; keduanya memakai alasan yang sama.
    if (selisih.value !== 0) await post(`/stok/${props.sparepart.id}/penyesuaian`, { stok_baru: stokBaru.value, keterangan: ket.value })
    if (hppBerubah.value) await post(`/stok/${props.sparepart.id}/koreksi-hpp`, { hpp_baru: hppBaru.value, keterangan: ket.value })
    toast.add({ severity: 'success', summary: 'Tersimpan', detail: 'Perubahan tercatat di kartu stok.', life: 3500 })
    visible.value = false
    emit('saved')
  } catch (e) {
    errors.value = fieldErrors(e)
    if (!Object.keys(errors.value).length) toast.add({ severity: 'error', summary: 'Gagal menyimpan', detail: errMsg(e), life: 5000 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal header="Penyesuaian Stok" :style="{ width: '28rem' }" :breakpoints="{ '600px': '94vw' }" :closable="!saving">
    <form v-if="sparepart" class="form" @submit.prevent="simpan">
      <div class="info">
        <strong>{{ sparepart.nama }}</strong>
        <span class="muted">Stok tercatat: <strong class="num">{{ fmtQty(sparepart.stok) }} {{ satuan }}</strong></span>
      </div>

      <div class="field">
        <label for="stok-baru">Stok hasil hitungan ({{ satuan }}) <span class="req">*</span></label>
        <InputNumber v-model="stokBaru" input-id="stok-baru" :min="0" :max-fraction-digits="2" locale="id-ID" fluid autofocus :invalid="!!errors.stok_baru" />
        <small v-if="errors.stok_baru" class="err">{{ errors.stok_baru }}</small>
        <small v-else-if="selisih !== null && selisih !== 0" class="selisih" :class="selisih > 0 ? 'plus' : 'minus'">
          Selisih {{ selisih > 0 ? '+' : '' }}{{ fmtQty(selisih) }} {{ satuan }} akan dicatat di kartu stok.
        </small>
        <small v-else class="hint">Hitung barang di rak, lalu isi jumlah yang sebenarnya.</small>
      </div>

      <div class="field">
        <label for="hpp-baru">HPP rata-rata per {{ satuan }}</label>
        <InputNumber v-model="hppBaru" input-id="hpp-baru" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid :invalid="!!errors.hpp_baru" />
        <small v-if="errors.hpp_baru" class="err">{{ errors.hpp_baru }}</small>
        <small v-else class="hint">Ubah hanya bila HPP salah (mis. salah ketik harga beli). Stok tidak ikut berubah.</small>
      </div>

      <div class="field">
        <label for="ket">Alasan <span class="req">*</span></label>
        <Textarea id="ket" v-model="ket" rows="2" auto-resize fluid maxlength="255" :invalid="!!errors.keterangan" placeholder="Mis. stok opname akhir bulan, barang rusak, barang ketemu" />
        <small v-if="errors.keterangan" class="err">{{ errors.keterangan }}</small>
      </div>

      <div class="actions">
        <Button type="button" label="Batal" severity="secondary" outlined :disabled="saving" @click="visible = false" />
        <Button type="submit" label="Simpan Penyesuaian" icon="pi pi-check" :loading="saving" :disabled="!adaPerubahan || ket.trim().length < 3" />
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
  display: flex;
  flex-direction: column;
  padding: 12px 14px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-primary-color) 6%, transparent);
}
.selisih {
  font-weight: 600;
}
.plus {
  color: var(--p-green-600);
}
.minus {
  color: var(--p-red-500);
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
