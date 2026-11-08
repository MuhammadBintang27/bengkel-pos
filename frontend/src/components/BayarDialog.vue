<script setup>
import { computed, ref, watch } from 'vue'
import { errMsg, fieldErrors } from '@/api/http'
import { METODE, rp, uangCepat } from '@/utils/format'
import { useToast } from 'primevue/usetoast'

// Dialog pembayaran dipakai kasir sparepart dan halaman detail transaksi.
// `kirim(metode, dibayar)` melakukan pembayaran ke server dan mengembalikan data transaksi lunas.
const props = defineProps({ total: { type: Number, required: true }, kirim: { type: Function, required: true } })
const visible = defineModel('visible', { default: false })
const emit = defineEmits(['paid', 'gagal'])

const toast = useToast()
const metode = ref('tunai')
const dibayar = ref(0)
const error = ref('')
const saving = ref(false)
const hasil = ref(null) // transaksi lunas, untuk layar sukses

const kembalian = computed(() => (metode.value === 'tunai' ? dibayar.value - props.total : 0))
const cukup = computed(() => (metode.value === 'tunai' ? dibayar.value >= props.total : true))
const cepat = computed(() => uangCepat(props.total))

watch(visible, (v) => {
  if (!v) return
  metode.value = 'tunai'
  dibayar.value = props.total
  error.value = ''
  hasil.value = null
})
watch(metode, (m) => (dibayar.value = m === 'tunai' ? Math.max(dibayar.value, props.total) : props.total))
// Total berubah di belakang dialog (mis. harga disegarkan setelah ditolak): uang diterima ikut menyesuaikan.
watch(
  () => props.total,
  (t) => {
    if (visible.value && !hasil.value && dibayar.value < t) dibayar.value = t
  },
)

async function bayar() {
  saving.value = true
  error.value = ''
  try {
    const data = await props.kirim(metode.value, metode.value === 'tunai' ? dibayar.value : props.total)
    hasil.value = data
    emit('paid', data)
  } catch (e) {
    error.value = Object.values(fieldErrors(e))[0] || errMsg(e)
    toast.add({ severity: 'error', summary: 'Pembayaran gagal', detail: error.value, life: 6000 })
    emit('gagal', e)
  } finally {
    saving.value = false
  }
}

const cetak = () => window.open(`/cetak/nota/${hasil.value.id}?print=1`, '_blank')
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="hasil ? 'Pembayaran Berhasil' : 'Pembayaran'" :style="{ width: '30rem' }" :breakpoints="{ '600px': '94vw' }" :closable="!saving">
    <!-- Sukses -->
    <div v-if="hasil" class="ok">
      <span class="ok-ico"><i class="pi pi-check" /></span>
      <h3>Transaksi lunas</h3>
      <p class="muted">{{ hasil.nomor }} · {{ METODE[hasil.metode_bayar].label }} · {{ rp(hasil.total) }}</p>
      <div v-if="hasil.metode_bayar === 'tunai'" class="kembali">
        <small>Kembalian</small>
        <strong class="num">{{ rp(hasil.kembalian) }}</strong>
      </div>
      <div class="ok-actions">
        <Button label="Cetak Nota" icon="pi pi-print" @click="cetak" />
        <Button label="Selesai" severity="secondary" outlined @click="visible = false" />
      </div>
    </div>

    <!-- Form -->
    <form v-else class="form" @submit.prevent="bayar">
      <div class="total">
        <small>Total tagihan</small>
        <strong class="num">{{ rp(total) }}</strong>
      </div>

      <div class="field">
        <label>Metode pembayaran</label>
        <div class="metode" role="radiogroup" aria-label="Metode pembayaran">
          <button v-for="(m, k) in METODE" :key="k" type="button" role="radio" class="m" :class="{ on: metode === k }" :aria-checked="metode === k" @click="metode = k">
            <i :class="m.icon" /> {{ m.label }}
          </button>
        </div>
      </div>

      <template v-if="metode === 'tunai'">
        <div class="field">
          <label for="uang">Uang diterima</label>
          <InputNumber v-model="dibayar" input-id="uang" mode="currency" currency="IDR" locale="id-ID" :max-fraction-digits="0" :min="0" fluid size="large" autofocus :invalid="!!error" />
          <div class="cepat">
            <Button label="Uang pas" size="small" severity="secondary" outlined @click="dibayar = total" />
            <Button v-for="u in cepat" :key="u" :label="rp(u)" size="small" severity="secondary" outlined @click="dibayar = u" />
          </div>
        </div>
        <div class="selisih" :class="cukup ? 'lebih' : 'kurang'" aria-live="polite">
          <span>{{ cukup ? 'Kembalian' : 'Kurang' }}</span>
          <strong class="num">{{ rp(Math.abs(kembalian)) }}</strong>
        </div>
      </template>
      <p v-else class="muted info">Pastikan {{ METODE[metode].label }} sebesar <strong>{{ rp(total) }}</strong> sudah diterima sebelum menekan Konfirmasi.</p>

      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

      <div class="actions">
        <Button type="button" label="Batal" severity="secondary" outlined :disabled="saving" @click="visible = false" />
        <Button type="submit" label="Konfirmasi Pembayaran" icon="pi pi-check" :loading="saving" :disabled="!cukup" />
      </div>
    </form>
  </Dialog>
</template>

<style scoped>
.form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.total {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 16px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--p-primary-color) 8%, transparent);
}
.total small {
  color: var(--p-text-muted-color);
}
.total strong {
  font-size: 2rem;
  color: var(--accent-text);
}
.metode {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.m {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 8px;
  border: 1px solid var(--p-content-border-color);
  border-radius: 10px;
  background: var(--p-content-background);
  color: var(--p-text-color);
  font: inherit;
  font-weight: 500;
  cursor: pointer;
}
.m:hover {
  border-color: var(--p-primary-color);
}
.m.on {
  border-color: var(--p-primary-color);
  box-shadow: 0 0 0 1px var(--p-primary-color);
  color: var(--accent-text);
  font-weight: 600;
}
.cepat {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 6px;
}
.selisih {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-radius: 10px;
  font-weight: 500;
}
.selisih strong {
  font-size: 1.3rem;
}
.lebih {
  background: color-mix(in srgb, var(--p-green-500) 12%, transparent);
  color: var(--p-green-700);
}
.kurang {
  background: color-mix(in srgb, var(--p-red-500) 12%, transparent);
  color: var(--p-red-600);
}
.app-dark .lebih {
  color: var(--p-green-300);
}
.app-dark .kurang {
  color: var(--p-red-300);
}
.info {
  margin: 0;
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.ok {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 8px 0;
  text-align: center;
}
.ok-ico {
  width: 64px;
  height: 64px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  font-size: 1.8rem;
  background: color-mix(in srgb, var(--p-green-500) 16%, transparent);
  color: var(--p-green-600);
  margin-bottom: 8px;
}
.ok h3 {
  font-size: 1.3rem;
}
.ok p {
  margin: 0;
}
.kembali {
  display: flex;
  flex-direction: column;
  margin: 16px 0 4px;
}
.kembali small {
  color: var(--p-text-muted-color);
}
.kembali strong {
  font-size: 2.2rem;
  color: var(--accent-text);
}
.ok-actions {
  display: flex;
  gap: 8px;
  margin-top: 20px;
}
</style>
