<script setup>
import { onMounted, ref } from 'vue'
import { errMsg, get } from '@/api/http'
import BarisSaldo from '@/components/BarisSaldo.vue'
import PageHeader from '@/components/PageHeader.vue'
import { rp, tgl } from '@/utils/format'

const d = ref(null)
const error = ref('')

onMounted(async () => {
  try {
    d.value = (await get('/mekanik/saldo')).data
  } catch (e) {
    error.value = errMsg(e)
  }
})

const slip = (id) => window.open(`/cetak/slip/${id}?print=1`, '_blank')
</script>

<template>
  <PageHeader title="Saldo Saya" subtitle="Komisi yang belum dicairkan dan riwayat pencairan." />

  <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

  <div v-else-if="!d" class="muat"><Skeleton height="96px" border-radius="12px" /><Skeleton height="260px" border-radius="12px" /></div>

  <div v-else class="susun">
    <div class="panel saldo">
      <span class="muted">Saldo belum cair</span>
      <strong class="num" :class="{ minus: d.saldo < 0 }">{{ d.saldo < 0 ? '-' : '' }}{{ rp(Math.abs(d.saldo)) }}</strong>
      <small v-if="d.saldo < 0" class="muted">Kasbon lebih besar dari komisi. Sisanya terpotong dari komisi berikutnya.</small>
    </div>

    <div class="panel">
      <h3 class="jud">Belum dicairkan</h3>
      <BarisSaldo :baris="d.baris" />
    </div>

    <div class="panel">
      <h3 class="jud">Riwayat pencairan</h3>
      <DataTable :value="d.pencairans" data-key="id" scrollable>
        <Column header="Nomor" style="min-width: 9rem"><template #body="{ data }">{{ data.nomor }}</template></Column>
        <Column header="Periode" style="min-width: 12rem"><template #body="{ data }">{{ tgl(data.periode_dari) }} - {{ tgl(data.periode_sampai) }}</template></Column>
        <Column header="Dibayar" style="min-width: 8rem" body-class="kanan" header-class="kanan"><template #body="{ data }"><strong class="num">{{ rp(data.dibayar) }}</strong></template></Column>
        <Column header="" style="width: 4rem" body-class="kanan"><template #body="{ data }"><Button icon="pi pi-print" severity="secondary" text rounded aria-label="Cetak slip" @click="slip(data.id)" /></template></Column>
        <template #empty><span class="muted">Belum ada pencairan.</span></template>
      </DataTable>
    </div>
  </div>
</template>

<style scoped>
.muat,
.susun {
  display: grid;
  gap: 16px;
}
.saldo {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 20px;
}
.saldo strong {
  font-size: 1.8rem;
}
.minus {
  color: var(--p-red-500);
}
.jud {
  margin: 0;
  padding: 16px 20px 10px;
  font-size: 1rem;
}
:deep(.kanan) {
  text-align: right;
}
:deep(.kanan .p-datatable-column-header-content) {
  justify-content: flex-end;
}
</style>
