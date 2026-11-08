<script setup>
import { computed } from 'vue'
import RemoteSelect from '@/components/RemoteSelect.vue'

// Satu field form dari definisi meta (config/master.php): label, input sesuai tipe, petunjuk, dan pesan error.
const props = defineProps({ field: { type: Object, required: true }, modelValue: null, error: String })
const emit = defineEmits(['update:modelValue'])

const v = computed({ get: () => props.modelValue, set: (x) => emit('update:modelValue', x) })
const id = computed(() => `f-${props.field.key}`)
const full = computed(() => ['textarea', 'switch'].includes(props.field.type))
const upper = (e) => (v.value = e.target.value.toUpperCase())
</script>

<template>
  <div class="field" :class="{ full }">
    <label :for="id">{{ field.label }} <span v-if="field.required" class="req" title="Wajib diisi">*</span></label>

    <Textarea v-if="field.type === 'textarea'" :id="id" v-model="v" rows="2" auto-resize fluid :invalid="!!error" :placeholder="field.placeholder" />

    <InputNumber
      v-else-if="field.type === 'money'"
      v-model="v"
      :input-id="id"
      mode="currency"
      currency="IDR"
      locale="id-ID"
      :max-fraction-digits="0"
      :min="0"
      fluid
      :invalid="!!error"
    />
    <InputNumber v-else-if="field.type === 'percent'" v-model="v" :input-id="id" suffix=" %" :min="0" :max="100" :max-fraction-digits="2" fluid :invalid="!!error" />
    <InputNumber v-else-if="field.type === 'number'" v-model="v" :input-id="id" :use-grouping="false" :max-fraction-digits="0" fluid :invalid="!!error" />

    <RemoteSelect v-else-if="field.type === 'select' && field.remote" v-model="v" :jenis="field.remote" :input-id="id" :invalid="!!error" :placeholder="field.placeholder" />
    <Select
      v-else-if="field.type === 'select'"
      v-model="v"
      :input-id="id"
      :options="field.options"
      option-label="label"
      option-value="value"
      :placeholder="field.placeholder || 'Pilih...'"
      fluid
      :invalid="!!error"
    />

    <div v-else-if="field.type === 'switch'" class="sw">
      <ToggleSwitch v-model="v" :input-id="id" />
      <span>{{ v ? 'Ya' : 'Tidak' }}</span>
    </div>

    <Password v-else-if="field.type === 'password'" v-model="v" :input-id="id" :feedback="false" toggle-mask fluid :invalid="!!error" autocomplete="new-password" />

    <template v-else>
      <InputText
        :id="id"
        v-model="v"
        fluid
        :invalid="!!error"
        :placeholder="field.placeholder"
        :inputmode="field.type === 'tel' ? 'tel' : undefined"
        :list="field.suggest ? `${id}-list` : undefined"
        :class="{ plat: field.type === 'plat' }"
        autocomplete="off"
        @input="field.type === 'plat' ? upper($event) : null"
      />
      <datalist v-if="field.suggest" :id="`${id}-list`"><option v-for="s in field.suggest" :key="s" :value="s" /></datalist>
    </template>

    <small v-if="error" class="err">{{ error }}</small>
    <small v-else-if="field.hint" class="hint">{{ field.hint }}</small>
  </div>
</template>

<style scoped>
.full {
  grid-column: 1 / -1;
}
.sw {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
}
.plat {
  letter-spacing: 0.06em;
  font-weight: 600;
}
</style>
