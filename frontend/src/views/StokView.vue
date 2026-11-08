<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { get } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import PageHeader from '@/components/PageHeader.vue'
import PenyesuaianDialog from '@/components/PenyesuaianDialog.vue'
import StatCard from '@/components/StatCard.vue'
import TableSkeleton from '@/components/TableSkeleton.vue'
import { usePagedList } from '@/composables/usePagedList'
import { useAuthStore } from '@/stores/auth'
import { qty, rp } from '@/utils/format'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const list = usePagedList('/stok', { status: '', kategori_id: '', rak_id: '' })
const kategori = ref([])
const raks = ref([])
const opsiRak = computed(() => [{ id: 'kosong', nama: 'Tanpa rak' }, ...raks.value])
const ringkasan = computed(() => list.last.value?.ringkasan)

const STATUS = [
  { l: 'Semua', v: 'semua' },
  { l: 'Menipis', v: 'menipis' },
  { l: 'Habis', v: 'habis' },
]
const status = ref(['menipis', 'habis'].includes(route.query.status) ? route.query.status : 'semua')

const dialog = ref(false)
const dipilih = ref(null)
const bukaSesuai = (s) => {
  dipilih.value = s
  dialog.value = true
}

watch(status, (v) => {
  list.state.status = v === 'semua' ? '' : v
  list.reset()
})
watch(() => [list.state.kategori_id, list.state.rak_id], () => list.reset())

onMounted(async () => {
  list.state.status = status.value === 'semua' ? '' : status.value
  list.load()
  try {
    kategori.value = (await get('/master/kategori-sparepart', { per_page: 100 })).data
    raks.value = (await get('/master/rak', { per_page: 100 })).data
  } catch {
    /* filter tambahan tidak muncul; daftar tetap bisa dipakai */
  }
})

const tanda = (s) => (s.stok <= 0 ? { t: 'Habis', s: 'danger' } : s.stok_min > 0 && s.stok <= s.stok_min ? { t: 'Menipis', s: 'warn' } : null)
const kerangka = computed(() => list.loading.value && !list.rows.value.length)
const filter = computed(() => list.state.q || list.state.kategori_id || list.state.rak_id || status.value !== 'semua')
</script>

<template>
  <PageHeader title="Stok Sparepart" subtitle="Sisa barang di bengkel, peringatan stok menipis, dan riwayat pergerakannya.">
    <Button label="Terima Barang" icon="pi pi-truck" @click="router.push('/pembelian/baru')" />
  </PageHeader>

  <section class="stats">
    <template v-if="ringkasan">
      <StatCard icon="stok" label="Jenis barang aktif" :value="ringkasan.jenis" />
      <StatCard icon="stok" label="Stok menipis" :value="ringkasan.menipis" hint="Di bawah stok minimum" :tone="ringkasan.menipis ? 'orange' : 'primary'" />
      <StatCard icon="stok" label="Stok habis" :value="ringkasan.habis" hint="Tidak bisa dijual" :tone="ringkasan.habis ? 'red' : 'primary'" />
      <StatCard v-if="auth.isOwner" icon="kas" label="Nilai persediaan" :value="rp(ringkasan.nilai)" hint="Stok × HPP" tone="green" />
    </template>
    <template v-else><Skeleton v-for="i in 3" :key="i" height="92px" border-radius="12px" /></template>
  </section>

  <div class="panel">
    <div class="toolbar">
      <SelectButton v-model="status" :options="STATUS" option-label="l" option-value="v" :allow-empty="false" aria-label="Status stok" />
      <div class="row2">
        <IconField class="cari">
          <InputIcon class="pi pi-search" />
          <InputText v-model="list.state.q" placeholder="Cari nama, kode, rak, atau scan barcode..." fluid aria-label="Cari barang" @input="list.search" />
        </IconField>
        <Select v-model="list.state.kategori_id" :options="kategori" option-label="nama" option-value="id" placeholder="Semua kategori" show-clear class="filter" aria-label="Kategori" />
        <Select v-model="list.state.rak_id" :options="opsiRak" option-label="nama" option-value="id" placeholder="Semua rak" show-clear filter class="filter" aria-label="Rak" />
        <span class="muted total">{{ list.total.value }} barang</span>
      </div>
    </div>

    <Message v-if="list.error.value" severity="error" :closable="false" class="m-3">
      {{ list.error.value }} <a href="#" class="underline" @click.prevent="list.load">Coba lagi</a>
    </Message>

    <TableSkeleton v-if="kerangka" :cols="auth.isOwner ? 7 : 5" />
    <DataTable
      v-else
      :value="list.rows.value"
      lazy
      paginator
      :rows="list.state.per_page"
      :rows-per-page-options="[15, 30, 50]"
      :total-records="list.total.value"
      :loading="list.loading.value"
      :sort-field="list.state.sort || null"
      :sort-order="list.state.sort ? (list.state.order === 'desc' ? -1 : 1) : null"
      data-key="id"
      row-hover
      scrollable
      paginator-template="RowsPerPageDropdown PrevPageLink PageLinks NextPageLink CurrentPageReport"
      current-page-report-template="{first}-{last} dari {totalRecords}"
      @page="list.onPage"
      @sort="list.onSort"
      @row-click="router.push(`/stok/${$event.data.id}`)"
    >
      <Column field="kode" header="Kode" sortable style="min-width: 7rem">
        <template #body="{ data }"><span class="kode">{{ data.kode }}</span></template>
      </Column>
      <Column field="nama" header="Nama" sortable style="min-width: 15rem">
        <template #body="{ data }">
          <div class="nama">
            <strong>{{ data.nama }}</strong>
            <small class="muted">{{ [data.merk, data.kategori?.nama].filter(Boolean).join(' · ') }}</small>
          </div>
        </template>
      </Column>
      <Column field="rak" header="Rak" sortable style="min-width: 6rem">
        <template #body="{ data }">
          <span v-if="data.rak" class="rak"><i class="pi pi-map-marker" /> {{ data.rak.nama }}</span>
          <span v-else class="muted">-</span>
        </template>
      </Column>
      <Column field="stok" header="Stok" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <div class="stok">
            <span class="num"><strong>{{ qty(data.stok) }}</strong> {{ data.satuan_dasar?.nama }}</span>
            <Tag v-if="tanda(data)" :value="tanda(data).t" :severity="tanda(data).s" />
          </div>
        </template>
      </Column>
      <Column header="Minimum" style="min-width: 6rem">
        <template #body="{ data }"><span class="num muted">{{ data.stok_min > 0 ? qty(data.stok_min) : '-' }}</span></template>
      </Column>
      <Column v-if="auth.isOwner" header="HPP" style="min-width: 8rem">
        <template #body="{ data }"><span class="num">{{ rp(data.hpp) }}</span></template>
      </Column>
      <Column v-if="auth.isOwner" header="Nilai" style="min-width: 9rem">
        <template #body="{ data }"><span class="num">{{ rp(Math.round(Math.max(data.stok, 0) * data.hpp)) }}</span></template>
      </Column>
      <Column header="" style="width: 9rem">
        <template #body="{ data }">
          <div class="aksi" @click.stop>
            <Button icon="pi pi-history" text rounded severity="secondary" aria-label="Kartu stok" title="Kartu stok" @click="router.push(`/stok/${data.id}`)" />
            <Button icon="pi pi-truck" text rounded severity="secondary" aria-label="Terima barang ini" title="Terima barang ini" @click="router.push(`/pembelian/baru?barang=${data.id}`)" />
            <Button v-if="auth.isOwner" icon="pi pi-sliders-h" text rounded severity="secondary" aria-label="Sesuaikan stok" title="Sesuaikan stok" @click="bukaSesuai(data)" />
          </div>
        </template>
      </Column>

      <template #empty>
        <div v-if="!list.loading.value && !list.error.value" class="kosong">
          <BIcon name="stok" :size="40" />
          <strong>{{ filter ? 'Tidak ada barang yang cocok' : 'Belum ada sparepart' }}</strong>
          <span class="muted">{{ filter ? 'Ubah kata kunci atau filter.' : 'Tambahkan sparepart di Master Data lebih dulu.' }}</span>
        </div>
      </template>
    </DataTable>
  </div>

  <PenyesuaianDialog v-model:visible="dialog" :sparepart="dipilih" @saved="list.load" />
</template>

<style scoped>
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
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
.kode {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.88rem;
}
.nama {
  display: flex;
  flex-direction: column;
  gap: 2px;
  line-height: 1.35;
}
.stok {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.rak {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 1px 9px;
  border-radius: 6px;
  font-weight: 700;
  background: color-mix(in srgb, var(--p-text-color) 8%, transparent);
}
.rak .pi {
  font-size: 0.72rem;
  color: var(--accent-text);
}
.aksi {
  display: flex;
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
