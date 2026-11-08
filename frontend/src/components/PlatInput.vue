<script setup>
import { ref, watch } from 'vue'
import { get } from '@/api/http'

// Kotak plat: ketik bebas (plat baru didaftarkan otomatis) atau pilih motor yang sudah dikenal.
const plat = defineModel('plat', { type: String, default: '' })
const props = defineProps({ inputId: String, placeholder: { type: String, default: 'Plat nomor, mis. B 1234 XYZ' }, size: String, autofocus: Boolean, invalid: Boolean })
const emit = defineEmits(['pilih', 'enter'])

const input = ref('')
const hasil = ref([])
const auto = ref()
const terpilih = ref(null)

const nama = (k) => [k.merk, k.tipe].filter(Boolean).join(' ')

async function cari(e) {
  try {
    hasil.value = (await get('/cari/kendaraan', { q: e.query })).data
  } catch {
    hasil.value = []
  }
}

function pilih(e) {
  terpilih.value = e.value
  plat.value = e.value.plat
  emit('pilih', e.value)
}

// Diketik bebas: pilihan lama dilepas, plat mengikuti teks (huruf besar).
function ketik(v) {
  if (v && typeof v === 'object') return (input.value = v)
  const teks = String(v ?? '').toUpperCase()
  input.value = teks
  plat.value = teks
  if (terpilih.value) {
    terpilih.value = null
    emit('pilih', null)
  }
}

// Dikosongkan dari luar (setelah simpan): kosongkan juga tampilan.
watch(plat, (v) => {
  if (!v) {
    input.value = ''
    terpilih.value = null
  }
})

function enter(e) {
  // Enter saat ada pilihan yang disorot ditangani AutoComplete; di sini hanya teks bebas.
  if (typeof input.value === 'string') {
    auto.value?.hide()
    emit('enter', e)
  }
}
</script>

<template>
  <AutoComplete
    ref="auto"
    :model-value="input"
    :suggestions="hasil"
    option-label="plat"
    fluid
    :size="size"
    :input-id="inputId"
    :placeholder="placeholder"
    :invalid="invalid"
    :autofocus="autofocus"
    input-class="plat-input"
    @complete="cari"
    @option-select="pilih"
    @update:model-value="ketik"
    @keydown.enter="enter"
  >
    <template #option="{ option }">
      <div class="opt">
        <strong class="p">{{ option.plat }}</strong>
        <small class="muted">{{ [nama(option) || 'Data motor belum lengkap', option.pemilik].filter(Boolean).join(' · ') }}</small>
      </div>
    </template>
    <template #empty>
      <div class="kosong">Plat baru. Akan didaftarkan otomatis.</div>
    </template>
  </AutoComplete>
</template>

<style scoped>
.opt {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}
.p {
  letter-spacing: 0.06em;
}
.kosong {
  padding: 8px 12px;
  color: var(--p-text-muted-color);
}
:deep(.plat-input) {
  letter-spacing: 0.06em;
  font-weight: 600;
}
</style>
