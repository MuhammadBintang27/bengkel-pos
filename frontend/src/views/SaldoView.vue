<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { errMsg, get } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import PageHeader from '@/components/PageHeader.vue'
import TableSkeleton from '@/components/TableSkeleton.vue'
import { rp } from '@/utils/format'

const router = useRouter()
const rows = ref([])
const loading = ref(true)
const error = ref('')
const q = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    rows.value = (await get('/saldo')).data
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)

// Daftar mekanik kecil, jadi cari dan urut dikerjakan di browser.
const tampil = computed(() => {
  const k = q.value.trim().toLowerCase()
  return k ? rows.value.filter((m) => m.nama.toLowerCase().includes(k)) : rows.value
})
const uang = (n) => (n < 0 ? '-' : '') + rp(Math.abs(n))
const buka = (m) => router.push(`/saldo/${m.id}`)
</script>

<template>
  <PageHeader title="Saldo Mekanik" subtitle="Komisi yang belum dicairkan. Buka mekanik untuk mencairkan atau mencatat kasbon.">
    <Button label="Riwayat Pencairan" icon="pi pi-history" severity="secondary" outlined @click="router.push('/pencairan')" />
  </PageHeader>

  <div class="panel">
    <div class="toolbar">
      <IconField class="cari">
        <InputIcon class="pi pi-search" />
        <InputText v-model="q" placeholder="Cari mekanik..." fluid aria-label="Cari mekanik" />
      </IconField>
      <span class="muted total">{{ tampil.length }} mekanik</span>
    </div>

    <Message v-if="error" severity="error" :closable="false" class="m-3">{{ error }} <a href="#" class="underline" @click.prevent="load">Coba lagi</a></Message>

    <TableSkeleton v-if="loading && !rows.length" :cols="4" />
    <DataTable
      v-else
      :value="tampil"
      :loading="loading"
      data-key="id"
      row-hover
      scrollable
      paginator
      :rows="15"
      :rows-per-page-options="[15, 30, 50]"
      paginator-template="RowsPerPageDropdown PrevPageLink PageLinks NextPageLink CurrentPageReport"
      current-page-report-template="{first}-{last} dari {totalRecords}"
      @row-click="buka($event.data)"
    >
      <Column field="nama" header="Mekanik" sortable style="min-width: 10rem">
        <template #body="{ data }"><strong>{{ data.nama }}</strong> <Tag v-if="!data.aktif" value="Nonaktif" severity="secondary" /></template>
      </Column>
      <Column field="saldo" header="Saldo belum cair" sortable style="min-width: 10rem" body-class="kanan" header-class="kanan">
        <template #body="{ data }"><strong class="num" :class="{ minus: data.saldo < 0 }">{{ uang(data.saldo) }}</strong></template>
      </Column>
      <Column field="kasbon" header="Kasbon belum terpotong" sortable style="min-width: 12rem" body-class="kanan" header-class="kanan">
        <template #body="{ data }"><span class="num">{{ rp(data.kasbon) }}</span> <small class="muted">/ {{ rp(data.batas_kasbon) }}</small></template>
      </Column>
      <Column header="" style="width: 8rem" body-class="kanan">
        <template #body="{ data }"><Button label="Buka" icon="pi pi-arrow-right" icon-pos="right" size="small" severity="secondary" outlined @click.stop="buka(data)" /></template>
      </Column>

      <template #empty>
        <div v-if="!loading && !error" class="kosong">
          <BIcon name="pelanggan" :size="40" />
          <strong>{{ q ? 'Tidak ada mekanik yang cocok' : 'Belum ada mekanik' }}</strong>
          <span class="muted">{{ q ? 'Ubah kata kunci.' : 'Tambahkan mekanik di Master Data.' }}</span>
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
  max-width: 420px;
}
.total {
  margin-left: auto;
}
.minus {
  color: var(--p-red-500);
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
:deep(.kanan) {
  text-align: right;
}
:deep(.kanan .p-datatable-column-header-content) {
  justify-content: flex-end;
}
:deep(tbody tr) {
  cursor: pointer;
}
</style>
