<script setup>
import { ref, watch } from 'vue'
import { rp } from '@/utils/format'

// Ubah jumlah (boleh desimal, mis. 1,5 liter) dan diskon satu baris keranjang.
const props = defineProps({ baris: Object })
const visible = defineModel('visible')
const emit = defineEmits(['simpan', 'hapus'])

const qty = ref(1)
const diskon = ref(0)

watch(
  () => props.baris,
  (b) => {
    if (!b) return
    qty.value = b.qty
    diskon.value = b.diskon
  },
)

const sub = () => Math.round((qty.value || 0) * (props.baris?.harga || 0))
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="baris?.nama" :style="{ width: '24rem' }" :breakpoints="{ '600px': '94vw' }">
    <form v-if="baris" class="form" @submit.prevent="emit('simpan', baris, { qty, diskon: diskon || 0 }), (visible = false)">
      <p class="muted info">{{ rp(baris.harga) }} per {{ baris.satuan }}</p>
      <div class="field">
        <label for="e-qty">Jumlah ({{ baris.satuan }})</label>
        <InputNumber v-model="qty" input-id="e-qty" :min="0" :max-fraction-digits="2" locale="id-ID" fluid autofocus />
      </div>
      <div class="field">
        <label for="e-diskon">Potongan harga</label>
        <InputNumber v-model="diskon" input-id="e-diskon" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" :max="sub()" fluid />
        <small class="hint">Mengurangi harga baris ini. Subtotal: {{ rp(sub() - (diskon || 0)) }}</small>
      </div>
      <div class="actions">
        <Button type="button" label="Hapus Barang" icon="pi pi-trash" severity="danger" text @click="emit('hapus', baris), (visible = false)" />
        <Button type="submit" label="Simpan" icon="pi pi-check" :disabled="!qty" />
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
  margin: -4px 0 0;
}
.actions {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}
</style>
