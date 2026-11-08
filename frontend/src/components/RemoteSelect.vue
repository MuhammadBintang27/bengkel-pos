<script setup>
import { onMounted, ref, watch } from 'vue'
import { get } from '@/api/http'

// Dropdown dengan pencarian ke server (/master/{jenis}/opsi). Nilai = id; label diambil dari server.
const props = defineProps({
  modelValue: [Number, String, null],
  jenis: { type: String, required: true },
  placeholder: String,
  invalid: Boolean,
  inputId: String,
  // bebas: nama yang tidak ada di daftar boleh diketik; teksnya keluar lewat v-model:teks (id tetap null).
  bebas: Boolean,
})
const emit = defineEmits(['update:modelValue'])
const teks = defineModel('teks', { type: String, default: '' })

const selected = ref(null)
const suggestions = ref([])

const fetchOpsi = async (q = '', ids = []) => (await get(`/master/${props.jenis}/opsi`, { q, ids })).data

async function complete(e) {
  try {
    suggestions.value = await fetchOpsi(e.query)
  } catch {
    suggestions.value = []
  }
}

async function syncFromValue() {
  if (props.modelValue == null || props.modelValue === '') {
    // Mode bebas: teks yang sedang diketik jangan terhapus hanya karena pilihan dilepas.
    if (!(props.bebas && typeof selected.value === 'string')) selected.value = null
    return
  }
  if (selected.value?.value === props.modelValue) return
  try {
    selected.value = (await fetchOpsi('', [props.modelValue])).find((o) => o.value === props.modelValue) || null
  } catch {
    selected.value = null
  }
}

watch(() => props.modelValue, syncFromValue)
onMounted(syncFromValue)

const pick = (e) => {
  teks.value = ''
  emit('update:modelValue', e.value.value)
}
// Teks diketik lalu dihapus atau tidak cocok: kosongkan pilihan (mode bebas: simpan teksnya).
const typed = (v) => {
  selected.value = v
  if (!v || typeof v === 'string') {
    teks.value = props.bebas ? (v || '') : ''
    emit('update:modelValue', null)
  }
}
</script>

<template>
  <AutoComplete
    :model-value="selected"
    :suggestions="suggestions"
    option-label="label"
    dropdown
    dropdown-mode="blank"
    :force-selection="!bebas"
    fluid
    :input-id="inputId"
    :invalid="invalid"
    :placeholder="placeholder || 'Ketik untuk mencari...'"
    @complete="complete"
    @option-select="pick"
    @update:model-value="typed"
  >
    <template #option="{ option }">
      <div class="opt">
        <span>{{ option.label }}</span>
        <small v-if="option.extra" class="muted">{{ option.extra }}</small>
      </div>
    </template>
    <template #empty>{{ bebas ? 'Belum terdaftar. Akan dibuat sebagai data baru.' : 'Tidak ditemukan' }}</template>
  </AutoComplete>
</template>

<style scoped>
.opt {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}
</style>
