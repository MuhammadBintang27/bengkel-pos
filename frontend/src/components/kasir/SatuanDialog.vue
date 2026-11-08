<script setup>
import { computed, ref, watch } from 'vue'
import { useKasirStore } from '@/stores/kasir'
import { qty as fmtQty, rp } from '@/utils/format'

// Barang punya beberapa satuan (pcs, box, dus): pilih satuan dan jumlah sebelum masuk keranjang.
// tanpaKeranjang: stok apa adanya (bukan dikurangi isi keranjang kasir); aksi: tulisan tombol simpan.
const props = defineProps({ sparepart: Object, tanpaKeranjang: Boolean, aksi: { type: String, default: 'Masukkan ke Keranjang' } })
const visible = defineModel('visible')
const emit = defineEmits(['tambah'])

const kasir = useKasirStore()
const satuanId = ref(null)
const jumlah = ref(1)

watch(
  () => props.sparepart,
  (s) => {
    if (!s) return
    satuanId.value = s.satuan_dasar_id
    jumlah.value = 1
  },
)

const satuan = computed(() => props.sparepart?.satuans.find((x) => x.satuan_id === satuanId.value))
const subtotal = computed(() => (satuan.value ? Math.round((jumlah.value || 0) * satuan.value.harga_jual) : 0))
// Berapa satuan ini yang masih bisa dijual dari stok yang ada.
const sisaDasar = computed(() => (props.tanpaKeranjang ? Number(props.sparepart.stok) : kasir.sisaDasar(props.sparepart.id, props.sparepart.stok)))
const tersedia = (x) => Math.floor(Math.round((sisaDasar.value / x.isi) * 100) / 100)
const cukup = computed(() => satuan.value && (jumlah.value || 0) * satuan.value.isi <= sisaDasar.value + 0.0001)

function tambah() {
  emit('tambah', props.sparepart, satuan.value, jumlah.value)
  visible.value = false
}
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="sparepart?.nama" :style="{ width: '28rem' }" :breakpoints="{ '600px': '94vw' }">
    <form v-if="sparepart" class="form" @submit.prevent="tambah">
      <p v-if="sparepart.lokasi" class="lokasi"><i class="pi pi-map-marker" /> Ambil di <strong>Rak {{ sparepart.lokasi }}</strong></p>
      <div class="field">
        <label>Pilih satuan</label>
        <div class="unit-grid" role="radiogroup" aria-label="Satuan">
          <button v-for="x in sparepart.satuans" :key="x.satuan_id" type="button" role="radio" class="unit" :class="{ on: x.satuan_id === satuanId }" :aria-checked="x.satuan_id === satuanId" :disabled="tersedia(x) < 1" @click="satuanId = x.satuan_id">
            <strong>{{ x.nama }}</strong>
            <span class="num">{{ rp(x.harga_jual) }}</span>
            <small class="muted">{{ x.isi === 1 ? `Stok ${fmtQty(tersedia(x))}` : `isi ${fmtQty(x.isi)} ${sparepart.satuan_dasar} · stok ${fmtQty(tersedia(x))}` }}</small>
          </button>
        </div>
      </div>

      <div class="field">
        <label for="qty-satuan">Jumlah</label>
        <div class="qty">
          <Button type="button" icon="pi pi-minus" severity="secondary" outlined rounded :disabled="jumlah <= 1" aria-label="Kurangi" @click="jumlah = Math.max(1, (jumlah || 1) - 1)" />
          <InputNumber v-model="jumlah" input-id="qty-satuan" :min="0" :max-fraction-digits="2" locale="id-ID" class="qty-input" autofocus />
          <Button type="button" icon="pi pi-plus" severity="secondary" outlined rounded aria-label="Tambah" @click="jumlah = (jumlah || 0) + 1" />
        </div>
        <small v-if="!cukup" class="err">Stok tidak cukup untuk jumlah ini.</small>
      </div>

      <div class="total"><span>Subtotal</span><strong class="num">{{ rp(subtotal) }}</strong></div>

      <div class="actions">
        <Button type="button" label="Batal" severity="secondary" outlined @click="visible = false" />
        <Button type="submit" :label="aksi" icon="pi pi-plus" :disabled="!cukup || !jumlah" />
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
.lokasi {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: -4px 0 0;
  color: var(--p-text-muted-color);
}
.lokasi .pi {
  color: var(--accent-text);
}
.unit-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 8px;
}
.unit {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 10px 12px;
  border: 1px solid var(--p-content-border-color);
  border-radius: 10px;
  background: var(--p-content-background);
  color: var(--p-text-color);
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.unit:hover:not(:disabled) {
  border-color: var(--p-primary-color);
}
.unit.on {
  border-color: var(--p-primary-color);
  box-shadow: 0 0 0 1px var(--p-primary-color);
}
.unit:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.qty {
  display: flex;
  align-items: center;
  gap: 10px;
}
.qty-input {
  flex: 1;
}
.qty-input :deep(input) {
  width: 100%;
  text-align: center;
  font-size: 1.2rem;
  font-weight: 600;
}
.total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding: 12px 14px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-primary-color) 8%, transparent);
}
.total strong {
  font-size: 1.5rem;
  color: var(--accent-text);
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
