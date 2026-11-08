<script setup>
import { computed, ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import { errMsg, fieldErrors, post } from '@/api/http'

const props = defineProps({ transaksi: { type: Object, required: true } })
const visible = defineModel('visible', { default: false })
const emit = defineEmits(['batal'])

const toast = useToast()
const alasan = ref('')
const error = ref('')
const saving = ref(false)

const lunas = computed(() => props.transaksi.status === 'lunas')
const adaBarang = computed(() => props.transaksi.spareparts?.length > 0)

watch(visible, (v) => {
  if (v) {
    alasan.value = ''
    error.value = ''
  }
})

async function submit() {
  saving.value = true
  error.value = ''
  try {
    const res = await post(`/transaksi/${props.transaksi.id}/batal`, { alasan: alasan.value })
    toast.add({ severity: 'success', summary: 'Dibatalkan', detail: res.message, life: 3500 })
    visible.value = false
    emit('batal', res.data)
  } catch (e) {
    error.value = Object.values(fieldErrors(e))[0] || errMsg(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal header="Batalkan Transaksi" :style="{ width: '30rem' }" :breakpoints="{ '600px': '94vw' }" :closable="!saving">
    <form class="form" @submit.prevent="submit">
      <Message severity="warn" :closable="false">
        <div>
          <strong>{{ transaksi.nomor }}</strong> akan dibatalkan dan tidak bisa dibuka lagi.
          <ul>
            <li v-if="adaBarang">Stok barang dikembalikan.</li>
            <li v-if="lunas">Komisi mekanik dikoreksi (dikurangi dari saldo).</li>
            <li>Datanya tetap tersimpan sebagai riwayat.</li>
          </ul>
        </div>
      </Message>

      <div class="field">
        <label for="alasan">Alasan pembatalan <span class="req">*</span></label>
        <Textarea id="alasan" v-model="alasan" rows="3" auto-resize fluid :invalid="!!error" placeholder="Contoh: pelanggan tidak jadi servis, salah input harga" autofocus />
        <small v-if="error" class="err">{{ error }}</small>
      </div>

      <div class="actions">
        <Button type="button" label="Kembali" severity="secondary" outlined :disabled="saving" @click="visible = false" />
        <Button type="submit" label="Batalkan Transaksi" icon="pi pi-times" severity="danger" :loading="saving" :disabled="alasan.trim().length < 3" />
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
ul {
  margin: 6px 0 0;
  padding-left: 18px;
  list-style: disc;
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
