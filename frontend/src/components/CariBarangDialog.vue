<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { get } from '@/api/http'
import TableSkeleton from '@/components/TableSkeleton.vue'
import { usePagedList } from '@/composables/usePagedList'
import { qty } from '@/utils/format'

// Telusuri seluruh daftar barang untuk layar penerimaan: cari, saring, lalu tambah satu per satu tanpa menutup dialog.
const visible = defineModel('visible', { type: Boolean, default: false })
// dipilih: { [sparepart_id]: jumlah baris yang sudah ditambahkan }, supaya terlihat mana yang sudah masuk
defineProps({ dipilih: { type: Object, default: () => ({}) } })
const emit = defineEmits(['tambah', 'baru'])

const list = usePagedList('/cari/sparepart', { kategori_id: '', stok: '', beli: 1 })
list.state.per_page = 8
const kategori = ref([])
const tampilan = ref('semua')
const TAMPIL = [{ l: 'Semua barang', v: 'semua' }, { l: 'Perlu dibeli', v: 'perlu' }]

onMounted(async () => {
  try {
    kategori.value = (await get('/cari/kategori')).data
  } catch {
    /* filter kategori opsional */
  }
})

watch(visible, (v) => {
  if (v) list.reset()
})
watch(tampilan, (v) => {
  list.state.stok = v === 'perlu' ? 'perlu' : ''
  list.reset()
})

const kerangka = computed(() => list.loading.value && !list.rows.value.length)
const status = (s) => (s.stok <= 0 ? { l: 'Habis', s: 'danger' } : s.stok <= s.stok_min ? { l: 'Menipis', s: 'warn' } : null)
</script>

<template>
  <Dialog v-model:visible="visible" modal header="Pilih Barang" :style="{ width: '46rem' }" :breakpoints="{ '760px': '96vw' }">
    <div class="atas">
      <IconField class="cari">
        <InputIcon class="pi pi-search" />
        <InputText v-model="list.state.q" placeholder="Cari nama, kode, merk, atau rak..." fluid aria-label="Cari barang" @input="list.search" />
      </IconField>
      <Select v-model="list.state.kategori_id" :options="kategori" option-label="nama" option-value="id" placeholder="Semua kategori" show-clear class="kat" aria-label="Kategori" @change="list.reset" />
    </div>
    <SelectButton v-model="tampilan" :options="TAMPIL" option-label="l" option-value="v" :allow-empty="false" class="mb" aria-label="Tampilan" />

    <TableSkeleton v-if="kerangka" :cols="3" />
    <DataTable
      v-else
      :value="list.rows.value" lazy paginator :rows="list.state.per_page" :total-records="list.total.value" :loading="list.loading.value"
      data-key="id" scrollable paginator-template="PrevPageLink PageLinks NextPageLink CurrentPageReport"
      current-page-report-template="{first}-{last} dari {totalRecords}" @page="list.onPage"
    >
      <Column header="Barang" style="min-width: 14rem">
        <template #body="{ data }">
          <div class="dua">
            <strong>{{ data.nama }} <small v-if="data.merk" class="muted">{{ data.merk }}</small></strong>
            <small class="muted">{{ data.kode }}<template v-if="data.lokasi"> · Rak {{ data.lokasi }}</template></small>
          </div>
        </template>
      </Column>
      <Column header="Stok" style="min-width: 8rem">
        <template #body="{ data }">
          <div class="dua">
            <span class="num">{{ qty(data.stok) }} {{ data.satuan_dasar }}</span>
            <Tag v-if="status(data)" :value="status(data).l" :severity="status(data).s" />
          </div>
        </template>
      </Column>
      <Column header="" style="width: 9rem" body-class="kanan">
        <template #body="{ data }">
          <Button v-if="dipilih[data.id]" :label="`Ditambah (${dipilih[data.id]})`" icon="pi pi-check" size="small" severity="success" outlined @click="emit('tambah', data)" />
          <Button v-else label="Tambah" icon="pi pi-plus" size="small" @click="emit('tambah', data)" />
        </template>
      </Column>
      <template #empty>
        <div v-if="!list.loading.value" class="kosong muted">{{ tampilan === 'perlu' ? 'Tidak ada barang yang perlu dibeli.' : 'Barang tidak ditemukan.' }}</div>
      </template>
    </DataTable>

    <template #footer>
      <Button label="Barang tidak ada? Tambah barang baru" icon="pi pi-plus" text @click="emit('baru', list.state.q)" />
      <Button label="Selesai" icon="pi pi-check" @click="visible = false" />
    </template>
  </Dialog>
</template>

<style scoped>
.atas {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 12px;
}
.cari {
  flex: 1 1 240px;
}
.kat {
  min-width: 13rem;
}
.mb {
  margin-bottom: 12px;
}
.dua {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  line-height: 1.35;
}
.kosong {
  padding: 28px 0;
  text-align: center;
}
:deep(.kanan) {
  text-align: right;
}
</style>
