<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'
import TableSkeleton from '@/components/TableSkeleton.vue'
import { usePagedList } from '@/composables/usePagedList'
import { rp, tgl } from '@/utils/format'

const router = useRouter()
const list = usePagedList('/pencairan', {})
onMounted(list.load)
</script>

<template>
  <PageHeader title="Riwayat Pencairan" subtitle="Semua pencairan komisi mekanik.">
    <Button label="Saldo Mekanik" icon="pi pi-users" severity="secondary" outlined @click="router.push('/saldo')" />
  </PageHeader>

  <div class="panel">
    <Message v-if="list.error.value" severity="error" :closable="false" class="m-3">{{ list.error.value }} <a href="#" class="underline" @click.prevent="list.load">Coba lagi</a></Message>
    <TableSkeleton v-if="list.loading.value && !list.rows.value.length" :cols="4" />
    <DataTable
      v-else
      :value="list.rows.value" lazy paginator :rows="list.state.per_page" :rows-per-page-options="[15, 30, 50]"
      :total-records="list.total.value" :loading="list.loading.value" data-key="id" row-hover scrollable
      paginator-template="RowsPerPageDropdown PrevPageLink PageLinks NextPageLink CurrentPageReport"
      current-page-report-template="{first}-{last} dari {totalRecords}"
      @page="list.onPage" @row-click="router.push(`/pencairan/${$event.data.id}`)"
    >
      <Column header="Nomor" style="min-width: 10rem">
        <template #body="{ data }"><strong>{{ data.nomor }}</strong> <Tag v-if="data.batal_at" value="Batal" severity="danger" /></template>
      </Column>
      <Column header="Mekanik" style="min-width: 9rem"><template #body="{ data }">{{ data.mekanik?.nama }}</template></Column>
      <Column header="Periode" style="min-width: 12rem"><template #body="{ data }">{{ tgl(data.periode_dari) }} - {{ tgl(data.periode_sampai) }}</template></Column>
      <Column header="Dibayar" style="min-width: 8rem" body-class="kanan" header-class="kanan">
        <template #body="{ data }"><strong class="num">{{ rp(data.dibayar) }}</strong></template>
      </Column>
      <template #empty><span v-if="!list.loading.value" class="muted pad">Belum ada pencairan.</span></template>
    </DataTable>
  </div>
</template>

<style scoped>
.pad {
  display: block;
  padding: 24px 16px;
  text-align: center;
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
