<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BIcon from '@/components/BIcon.vue'
import PageHeader from '@/components/PageHeader.vue'
import TableSkeleton from '@/components/TableSkeleton.vue'
import { usePagedList } from '@/composables/usePagedList'
import { rp, STATUS_BELI, tgl, ymd } from '@/utils/format'

const router = useRouter()
const route = useRoute()

const list = usePagedList('/pembelian', { status: '', tempo: '', dari: '', sampai: '' })
const tanggal = ref(null)

const TAB = [
  { l: 'Semua', v: 'semua' },
  { l: 'Draft', v: 'draft' },
  { l: 'Diterima', v: 'diterima' },
  { l: 'Tempo belum lunas', v: 'tempo' },
  { l: 'Batal', v: 'batal' },
]
const tab = ref(route.query.tempo ? 'tempo' : 'semua')

function terapkanTab() {
  list.state.status = ['draft', 'diterima', 'batal'].includes(tab.value) ? tab.value : ''
  list.state.tempo = tab.value === 'tempo' ? 1 : ''
}
watch(tab, () => {
  terapkanTab()
  list.reset()
})
watch(tanggal, (v) => {
  list.state.dari = v?.[0] ? ymd(v[0]) : ''
  list.state.sampai = v?.[1] ? ymd(v[1]) : v?.[0] ? ymd(v[0]) : ''
  if (!v || v[1] || !v[0]) list.reset()
})

onMounted(() => {
  terapkanTab()
  list.load()
})

const kerangka = computed(() => list.loading.value && !list.rows.value.length)
const filter = computed(() => list.state.q || list.state.dari || tab.value !== 'semua')
const tempoTotal = computed(() => (tab.value === 'tempo' ? list.rows.value.reduce((s, p) => s + p.total, 0) : 0))
</script>

<template>
  <PageHeader title="Penerimaan Barang" subtitle="Barang yang datang dari supplier. Stok dan harga pokok diperbarui saat barang diterima.">
    <Button label="Terima Barang" icon="pi pi-plus" @click="router.push('/pembelian/baru')" />
  </PageHeader>

  <div class="panel">
    <div class="toolbar">
      <SelectButton v-model="tab" :options="TAB" option-label="l" option-value="v" :allow-empty="false" aria-label="Status" />
      <div class="row2">
        <IconField class="cari">
          <InputIcon class="pi pi-search" />
          <InputText v-model="list.state.q" placeholder="Cari nomor, nomor faktur, atau supplier..." fluid aria-label="Cari penerimaan" @input="list.search" />
        </IconField>
        <DatePicker v-model="tanggal" selection-mode="range" date-format="dd/mm/yy" placeholder="Semua tanggal faktur" show-icon show-button-bar :manual-input="false" class="filter" aria-label="Rentang tanggal" />
        <span v-if="tempoTotal" class="utang">Utang ke supplier di halaman ini: <strong class="num">{{ rp(tempoTotal) }}</strong></span>
        <span class="muted total">{{ list.total.value }} penerimaan</span>
      </div>
    </div>

    <Message v-if="list.error.value" severity="error" :closable="false" class="m-3">
      {{ list.error.value }} <a href="#" class="underline" @click.prevent="list.load">Coba lagi</a>
    </Message>

    <TableSkeleton v-if="kerangka" :cols="6" />
    <DataTable
      v-else
      :value="list.rows.value"
      lazy
      paginator
      :rows="list.state.per_page"
      :rows-per-page-options="[15, 30, 50]"
      :total-records="list.total.value"
      :loading="list.loading.value"
      data-key="id"
      row-hover
      scrollable
      paginator-template="RowsPerPageDropdown PrevPageLink PageLinks NextPageLink CurrentPageReport"
      current-page-report-template="{first}-{last} dari {totalRecords}"
      @page="list.onPage"
      @row-click="router.push(`/pembelian/${$event.data.id}`)"
    >
      <Column header="Nomor" style="min-width: 10rem">
        <template #body="{ data }">
          <div class="dua">
            <strong>{{ data.nomor }}</strong>
            <small class="muted">Faktur {{ tgl(data.tanggal_faktur) }}<template v-if="data.no_faktur"> · {{ data.no_faktur }}</template></small>
          </div>
        </template>
      </Column>
      <Column header="Supplier" style="min-width: 12rem">
        <template #body="{ data }">
          <div class="dua">
            <span>{{ data.supplier?.nama }}</span>
            <small class="muted">{{ data.items_count }} jenis barang</small>
          </div>
        </template>
      </Column>
      <Column header="Status" style="min-width: 9rem">
        <template #body="{ data }"><Tag :value="STATUS_BELI[data.status].label" :severity="STATUS_BELI[data.status].severity" /></template>
      </Column>
      <Column header="Pembayaran" style="min-width: 11rem">
        <template #body="{ data }">
          <span v-if="data.status !== 'diterima'" class="muted">-</span>
          <Tag v-else-if="data.status_bayar === 'lunas'" value="Lunas" severity="success" />
          <div v-else class="dua">
            <Tag :value="data.terlambat ? 'Terlambat' : 'Tempo'" :severity="data.terlambat ? 'danger' : 'warn'" />
            <small :class="data.terlambat ? 'merah' : 'muted'">Jatuh tempo {{ tgl(data.jatuh_tempo) }}</small>
          </div>
        </template>
      </Column>
      <Column header="Total" style="min-width: 8rem" body-class="kanan" header-class="kanan">
        <template #body="{ data }"><strong class="num">{{ rp(data.total) }}</strong></template>
      </Column>
      <Column header="Dicatat oleh" style="min-width: 8rem">
        <template #body="{ data }"><span class="muted">{{ data.user?.nama }}</span></template>
      </Column>

      <template #empty>
        <div v-if="!list.loading.value && !list.error.value" class="kosong">
          <BIcon name="truk" :size="40" />
          <strong>{{ filter ? 'Tidak ada penerimaan yang cocok' : 'Belum ada penerimaan barang' }}</strong>
          <span class="muted">{{ filter ? 'Ubah kata kunci atau filter.' : 'Catat barang yang datang dari supplier agar stok bertambah.' }}</span>
          <Button v-if="!filter" label="Terima Barang" icon="pi pi-plus" class="mt-2" @click="router.push('/pembelian/baru')" />
        </div>
      </template>
    </DataTable>
  </div>
</template>

<style scoped>
.toolbar {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--p-content-border-color);
}
.row2 {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}
.cari {
  flex: 1 1 280px;
  max-width: 420px;
}
.filter {
  min-width: 13rem;
}
.utang {
  padding: 4px 12px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--p-orange-500) 14%, transparent);
}
.total {
  margin-left: auto;
}
.dua {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  line-height: 1.35;
}
.merah {
  color: var(--p-red-500);
  font-weight: 600;
}
:deep(.kanan) {
  text-align: right;
  justify-content: flex-end;
}
:deep(tbody tr) {
  cursor: pointer;
}
.kosong {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 40px 16px;
  text-align: center;
  color: var(--p-text-muted-color);
}
.kosong strong {
  color: var(--p-text-color);
  font-size: 1.05rem;
}
</style>
