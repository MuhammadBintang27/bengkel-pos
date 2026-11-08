<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import BIcon from '@/components/BIcon.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusTag from '@/components/StatusTag.vue'
import TableSkeleton from '@/components/TableSkeleton.vue'
import { usePagedList } from '@/composables/usePagedList'
import { rp, tglJam, ymd } from '@/utils/format'

const router = useRouter()
const list = usePagedList('/transaksi', { status: '', jenis: '', dari: '', sampai: '' })
const tanggal = ref(null) // [dari, sampai] dari DatePicker

const STATUS = [
  { l: 'Semua', v: 'semua' },
  { l: 'Sedang berjalan', v: 'terbuka' },
  { l: 'Lunas', v: 'lunas' },
  { l: 'Batal', v: 'batal' },
]
const status = ref('terbuka')
const JENIS = [
  { l: 'Servis', v: 'servis' },
  { l: 'Penjualan', v: 'penjualan' },
]

watch(status, (v) => {
  list.state.status = v === 'semua' ? '' : v
  list.reset()
})
watch(() => list.state.jenis, () => list.reset())
watch(tanggal, (v) => {
  list.state.dari = v?.[0] ? ymd(v[0]) : ''
  list.state.sampai = v?.[1] ? ymd(v[1]) : v?.[0] ? ymd(v[0]) : ''
  if (!v || v[1] || !v[0]) list.reset()
})

onMounted(() => {
  list.state.status = 'terbuka'
  list.load()
})

const kerangka = computed(() => list.loading.value && !list.rows.value.length)
const filter = computed(() => list.state.q || list.state.jenis || list.state.dari || status.value !== 'terbuka')
</script>

<template>
  <PageHeader title="Daftar Transaksi" subtitle="Servis dan penjualan, dari yang sedang dikerjakan sampai yang sudah lunas.">
    <Button label="Kasir Sparepart" icon="pi pi-shopping-cart" severity="secondary" outlined @click="router.push('/kasir')" />
    <Button label="Papan Servis" icon="pi pi-th-large" @click="router.push('/servis')" />
  </PageHeader>

  <div class="panel">
    <div class="toolbar">
      <SelectButton v-model="status" :options="STATUS" option-label="l" option-value="v" :allow-empty="false" aria-label="Status" />
      <div class="row2">
        <IconField class="cari">
          <InputIcon class="pi pi-search" />
          <InputText v-model="list.state.q" placeholder="Cari nomor, plat, atau nama pelanggan..." fluid aria-label="Cari transaksi" @input="list.search" />
        </IconField>
        <Select v-model="list.state.jenis" :options="JENIS" option-label="l" option-value="v" placeholder="Semua jenis" show-clear class="filter" aria-label="Jenis" />
        <DatePicker v-model="tanggal" selection-mode="range" date-format="dd/mm/yy" placeholder="Semua tanggal" show-icon show-button-bar :manual-input="false" class="filter" aria-label="Rentang tanggal" />
        <span class="muted total">{{ list.total.value }} transaksi</span>
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
      @row-click="router.push(`/transaksi/${$event.data.id}`)"
    >
      <Column header="Nomor" style="min-width: 10rem">
        <template #body="{ data }">
          <div class="nomor">
            <strong>{{ data.nomor }}</strong>
            <small class="muted">{{ tglJam(data.created_at) }}</small>
          </div>
        </template>
      </Column>
      <Column header="Motor / Pelanggan" style="min-width: 14rem">
        <template #body="{ data }">
          <div v-if="data.kendaraan" class="motor">
            <span class="plat">{{ data.kendaraan.plat }}</span>
            <small class="muted">{{ [[data.kendaraan.merk, data.kendaraan.tipe].filter(Boolean).join(' ') || 'Data motor belum lengkap', data.pelanggan?.nama].filter(Boolean).join(' · ') }}</small>
          </div>
          <span v-else-if="data.pelanggan">{{ data.pelanggan.nama }}</span>
          <span v-else class="muted">Pembeli umum</span>
        </template>
      </Column>
      <Column header="Jenis" style="width: 8rem">
        <template #body="{ data }">
          <span class="jenis"><BIcon :name="data.jenis === 'servis' ? 'servis' : 'jual'" :size="16" /> {{ data.jenis === 'servis' ? 'Servis' : 'Penjualan' }}</span>
        </template>
      </Column>
      <Column header="Status" style="width: 9rem">
        <template #body="{ data }"><StatusTag :status="data.status" :jenis="data.jenis" /></template>
      </Column>
      <Column header="Total" style="min-width: 8rem" body-class="kanan" header-class="kanan">
        <template #body="{ data }"><strong class="num">{{ rp(data.total) }}</strong></template>
      </Column>
      <Column header="Kasir" style="min-width: 7rem">
        <template #body="{ data }"><span class="muted">{{ data.user?.nama }}</span></template>
      </Column>

      <template #empty>
        <div v-if="!list.loading.value && !list.error.value" class="kosong">
          <BIcon name="nota" :size="40" />
          <strong>{{ filter ? 'Tidak ada transaksi yang cocok' : 'Belum ada transaksi yang berjalan' }}</strong>
          <span class="muted">{{ filter ? 'Ubah kata kunci atau filter.' : 'Mulai dengan mencatat motor yang masuk.' }}</span>
          <Button v-if="!filter" label="Papan Servis" icon="pi pi-th-large" class="mt-2" @click="router.push('/servis')" />
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
  min-width: 11rem;
}
.total {
  margin-left: auto;
}
.nomor,
.motor {
  display: flex;
  flex-direction: column;
  gap: 2px;
  line-height: 1.35;
}
.plat {
  font-weight: 700;
  letter-spacing: 0.05em;
}
.jenis {
  display: inline-flex;
  align-items: center;
  gap: 6px;
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
