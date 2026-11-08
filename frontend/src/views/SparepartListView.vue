<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { del, errMsg, get } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import TableSkeleton from '@/components/TableSkeleton.vue'
import PageHeader from '@/components/PageHeader.vue'
import { usePagedList } from '@/composables/usePagedList'
import { qty, rp } from '@/utils/format'

const router = useRouter()
const route = useRoute()
const toast = useToast()
const confirm = useConfirm()

const list = usePagedList('/sparepart', { kategori_id: '', rak_id: '', stok: '', aktif: '' })
const kategori = ref([])
const raks = ref([])
// 'kosong' = barang yang belum diberi rak
const opsiRak = computed(() => [{ id: 'kosong', nama: 'Tanpa rak' }, ...raks.value])
const STOK = [
  { label: 'Menipis', value: 'menipis' },
  { label: 'Habis', value: 'habis' },
]

onMounted(async () => {
  // dari master Rak: /sparepart?rak=3 langsung menampilkan isi rak itu
  if (route.query.rak) list.state.rak_id = route.query.rak === 'kosong' ? 'kosong' : Number(route.query.rak)
  list.load()
  try {
    raks.value = (await get('/master/rak', { per_page: 100 })).data
    kategori.value = (await get('/master/kategori-sparepart', { per_page: 100 })).data
  } catch {
    /* filter kategori tidak muncul; daftar tetap bisa dipakai */
  }
})
watch(() => [list.state.kategori_id, list.state.rak_id, list.state.stok, list.state.aktif], () => list.reset())

const dasar = (r) => r.satuans.find((s) => s.satuan_id === r.satuan_dasar_id)
const status = (r) => (r.stok <= 0 ? { t: 'Habis', s: 'danger' } : r.stok_min > 0 && r.stok <= r.stok_min ? { t: 'Menipis', s: 'warn' } : null)
const kerangka = computed(() => list.loading.value && !list.rows.value.length)
const filterAktif = computed(() => list.state.kategori_id || list.state.rak_id || list.state.stok || list.state.aktif || list.state.q)

function hapus(r) {
  confirm.require({
    header: 'Hapus sparepart?',
    message: `"${r.nama}" akan dihapus dari daftar. Riwayat transaksi yang sudah ada tidak berubah.`,
    icon: 'pi pi-trash',
    acceptProps: { label: 'Hapus', severity: 'danger' },
    rejectProps: { label: 'Batal', severity: 'secondary', outlined: true },
    accept: async () => {
      try {
        toast.add({ severity: 'success', summary: 'Dihapus', detail: (await del(`/sparepart/${r.id}`)).message, life: 3000 })
        await list.refresh()
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Tidak bisa dihapus', detail: errMsg(e), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <PageHeader title="Sparepart" subtitle="Barang yang dijual dan dipakai servis, lengkap dengan satuan dan harga jual.">
    <Button label="Tambah Sparepart" icon="pi pi-plus" @click="router.push('/sparepart/baru')" />
  </PageHeader>

  <div class="panel">
    <div class="toolbar">
      <IconField class="cari">
        <InputIcon class="pi pi-search" />
        <InputText v-model="list.state.q" placeholder="Cari nama, kode, merk, motor, atau scan barcode..." fluid aria-label="Cari sparepart" @input="list.search" />
      </IconField>
      <Select v-model="list.state.kategori_id" :options="kategori" option-label="nama" option-value="id" placeholder="Semua kategori" show-clear class="filter" aria-label="Kategori" />
      <Select v-model="list.state.rak_id" :options="opsiRak" option-label="nama" option-value="id" placeholder="Semua rak" show-clear filter class="filter" aria-label="Rak" />
      <Select v-model="list.state.stok" :options="STOK" option-label="label" option-value="value" placeholder="Semua stok" show-clear class="filter" aria-label="Status stok" />
      <Select
        v-model="list.state.aktif"
        :options="[{ l: 'Aktif saja', v: '1' }, { l: 'Nonaktif saja', v: '0' }]"
        option-label="l"
        option-value="v"
        placeholder="Aktif & nonaktif"
        show-clear
        class="filter"
        aria-label="Status"
      />
      <span class="muted total">{{ list.total.value }} sparepart</span>
    </div>

    <Message v-if="list.error.value" severity="error" :closable="false" class="m-3">
      {{ list.error.value }} <a href="#" class="underline" @click.prevent="list.load">Coba lagi</a>
    </Message>

    <TableSkeleton v-if="kerangka" :cols="7" />
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
      @row-click="router.push(`/sparepart/${$event.data.id}`)"
    >
      <Column field="kode" header="Kode" sortable style="min-width: 7rem">
        <template #body="{ data }"><span class="kode">{{ data.kode }}</span></template>
      </Column>
      <Column field="nama" header="Nama" sortable style="min-width: 16rem">
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
      <Column field="stok" header="Stok" sortable style="min-width: 9rem">
        <template #body="{ data }">
          <div class="stok">
            <span class="num"><strong>{{ qty(data.stok) }}</strong> {{ data.satuan_dasar?.nama }}</span>
            <Tag v-if="status(data)" :value="status(data).t" :severity="status(data).s" />
          </div>
        </template>
      </Column>
      <Column header="Harga jual" style="min-width: 11rem">
        <template #body="{ data }">
          <div class="harga">
            <span v-for="s in data.satuans.filter((x) => x.aktif)" :key="s.id" class="num">
              <strong>{{ rp(s.harga_jual) }}</strong> <small class="muted">/ {{ s.satuan?.nama }}<template v-if="s.isi !== 1"> (isi {{ qty(s.isi) }})</template></small>
            </span>
          </div>
        </template>
      </Column>
      <Column field="hpp" header="HPP" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <span class="num">{{ rp(data.hpp) }}</span>
          <small v-if="dasar(data)" class="muted"> /{{ data.satuan_dasar?.nama }}</small>
        </template>
      </Column>
      <Column header="Status" style="width: 6rem">
        <template #body="{ data }"><Tag :value="data.aktif ? 'Aktif' : 'Nonaktif'" :severity="data.aktif ? 'success' : 'secondary'" /></template>
      </Column>
      <Column header="" style="width: 7rem">
        <template #body="{ data }">
          <div class="aksi" @click.stop>
            <Button icon="pi pi-pencil" text rounded severity="secondary" aria-label="Ubah" @click="router.push(`/sparepart/${data.id}`)" />
            <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus" @click="hapus(data)" />
          </div>
        </template>
      </Column>

      <template #empty>
        <div v-if="!list.loading.value && !list.error.value" class="kosong">
          <BIcon name="sparepart" :size="40" />
          <strong>{{ filterAktif ? 'Tidak ada sparepart yang cocok' : 'Belum ada sparepart' }}</strong>
          <span class="muted">{{ filterAktif ? 'Ubah kata kunci atau filter.' : 'Tambahkan sparepart pertama, lengkap dengan satuan dan harga jualnya.' }}</span>
          <Button v-if="!filterAktif" label="Tambah Sparepart" icon="pi pi-plus" class="mt-2" @click="router.push('/sparepart/baru')" />
        </div>
      </template>
    </DataTable>
  </div>
</template>

<style scoped>
.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--p-content-border-color);
}
.cari {
  flex: 1 1 280px;
  max-width: 440px;
}
.filter {
  min-width: 11rem;
}
.total {
  margin-left: auto;
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
.kode {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.88rem;
}
.nama,
.harga {
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
.aksi {
  display: flex;
  justify-content: flex-end;
}
:deep(.p-datatable-selectable-row),
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
