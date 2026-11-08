<script setup>
import { rp, tgl } from '@/utils/format'

// Tabel rincian baris buku komisi (belum cair, atau isi satu pencairan).
defineProps({ baris: { type: Array, required: true } })

const JENIS = { komisi: { l: 'Komisi', s: 'success' }, koreksi: { l: 'Koreksi', s: 'danger' }, kasbon: { l: 'Kasbon', s: 'warn' } }
</script>

<template>
  <DataTable :value="baris" data-key="id" scrollable>
    <Column header="Tanggal" style="min-width: 7rem">
      <template #body="{ data }">{{ tgl(data.tanggal) }}</template>
    </Column>
    <Column header="Jenis" style="min-width: 6rem">
      <template #body="{ data }"><Tag :value="JENIS[data.jenis].l" :severity="JENIS[data.jenis].s" /></template>
    </Column>
    <Column header="Transaksi" style="min-width: 14rem">
      <template #body="{ data }">
        <router-link v-if="data.transaksi_id" :to="`/transaksi/${data.transaksi_id}`" class="underline">{{ data.nomor }}</router-link>
        <span v-else class="muted">{{ data.keterangan }}</span>
        <small v-if="data.plat || data.jasa" class="muted blok">{{ [data.plat, data.jasa].filter(Boolean).join(' · ') }}</small>
      </template>
    </Column>
    <Column header="Jumlah" style="min-width: 8rem" body-class="kanan" header-class="kanan">
      <template #body="{ data }"><strong class="num" :class="{ minus: data.jumlah < 0 }">{{ data.jumlah < 0 ? '-' : '' }}{{ rp(Math.abs(data.jumlah)) }}</strong></template>
    </Column>
    <template #empty><span class="muted">Tidak ada baris.</span></template>
  </DataTable>
</template>

<style scoped>
.blok {
  display: block;
}
.minus {
  color: var(--p-red-500);
}
:deep(.kanan) {
  text-align: right;
}
:deep(.kanan .p-datatable-column-header-content) {
  justify-content: flex-end;
}
</style>
