<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'
import PenyesuaianDialog from '@/components/PenyesuaianDialog.vue'
import StatCard from '@/components/StatCard.vue'
import { usePagedList } from '@/composables/usePagedList'
import { useAuthStore } from '@/stores/auth'
import { JENIS_STOK, qty, rp, tglJam } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const id = computed(() => route.params.id)
const list = usePagedList(() => `/stok/${id.value}/kartu`, { per_page: 20 })
const sp = computed(() => list.last.value?.sparepart)
const dialog = ref(false)
const satuan = computed(() => sp.value?.satuan_dasar?.nama || '')

onMounted(() => list.load())
watch(id, () => list.reset())

const tanda = computed(() => (!sp.value ? null : sp.value.stok <= 0 ? { t: 'Habis', s: 'danger' } : sp.value.stok_min > 0 && sp.value.stok <= sp.value.stok_min ? { t: 'Menipis', s: 'warn' } : null))

// Setiap baris menunjuk ke dokumen asalnya (transaksi atau penerimaan barang) bila ada.
const asal = (m) => (m.transaksi ? { teks: m.transaksi.nomor, to: `/transaksi/${m.transaksi_id}` } : m.pembelian ? { teks: m.pembelian.nomor, to: `/pembelian/${m.pembelian_id}` } : null)
</script>

<template>
  <PageHeader :title="sp ? sp.nama : 'Kartu Stok'" back="/stok" :subtitle="sp ? [sp.kode, sp.merk, sp.kategori?.nama].filter(Boolean).join(' · ') : ''">
    <Button label="Terima Barang" icon="pi pi-truck" severity="secondary" outlined :disabled="!sp" @click="router.push(`/pembelian/baru?barang=${id}`)" />
    <Button v-if="auth.isOwner" label="Sesuaikan Stok" icon="pi pi-sliders-h" :disabled="!sp" @click="dialog = true" />
  </PageHeader>

  <Message v-if="list.error.value" severity="error" :closable="false" class="mb-4">
    {{ list.error.value }} <a href="#" class="underline" @click.prevent="list.load">Coba lagi</a>
  </Message>

  <section class="stats">
    <template v-if="sp">
      <StatCard icon="stok" label="Stok sekarang" :value="`${qty(sp.stok)} ${satuan}`" :tone="tanda ? (tanda.s === 'danger' ? 'red' : 'orange') : 'primary'" :hint="tanda?.t" />
      <StatCard icon="stok" label="Stok minimum" :value="sp.stok_min > 0 ? `${qty(sp.stok_min)} ${satuan}` : '-'" hint="Batas peringatan menipis" />
      <StatCard icon="stok" label="Rak" :value="sp.rak?.nama || '-'" hint="Tempat penyimpanan" />
      <StatCard v-if="auth.isOwner && sp.hpp != null" icon="kas" label="HPP rata-rata" :value="rp(sp.hpp)" :hint="`per ${satuan}`" tone="green" />
    </template>
    <template v-else-if="!list.error.value"><Skeleton v-for="i in 3" :key="i" height="92px" border-radius="12px" /></template>
  </section>

  <div class="panel">
    <header class="judul"><h2>Riwayat pergerakan</h2><span class="muted">{{ list.total.value }} catatan</span></header>

    <DataTable
      :value="list.rows.value"
      lazy
      paginator
      :rows="list.state.per_page"
      :rows-per-page-options="[20, 50, 100]"
      :total-records="list.total.value"
      :loading="list.loading.value && !!list.rows.value.length"
      data-key="id"
      row-hover
      scrollable
      paginator-template="RowsPerPageDropdown PrevPageLink PageLinks NextPageLink CurrentPageReport"
      current-page-report-template="{first}-{last} dari {totalRecords}"
      @page="list.onPage"
    >
      <Column header="Waktu" style="min-width: 9rem">
        <template #body="{ data }"><span class="muted">{{ tglJam(data.created_at) }}</span></template>
      </Column>
      <Column header="Jenis" style="min-width: 10rem">
        <template #body="{ data }"><Tag :value="JENIS_STOK[data.jenis]?.label || data.jenis" :severity="JENIS_STOK[data.jenis]?.severity || 'secondary'" /></template>
      </Column>
      <Column header="Keterangan" style="min-width: 14rem">
        <template #body="{ data }">
          <div class="ket">
            <RouterLink v-if="asal(data)" :to="asal(data).to" class="tautan">{{ asal(data).teks }}</RouterLink>
            <span v-if="data.keterangan" class="muted">{{ data.keterangan }}</span>
          </div>
        </template>
      </Column>
      <Column header="Jumlah" style="min-width: 7rem" body-class="kanan" header-class="kanan">
        <template #body="{ data }"><strong class="num" :class="data.qty > 0 ? 'plus' : 'minus'">{{ data.qty > 0 ? '+' : '' }}{{ qty(data.qty) }}</strong></template>
      </Column>
      <Column header="Saldo" style="min-width: 7rem" body-class="kanan" header-class="kanan">
        <template #body="{ data }"><span class="num">{{ qty(data.saldo) }}</span></template>
      </Column>
      <Column header="Oleh" style="min-width: 8rem">
        <template #body="{ data }"><span class="muted">{{ data.user?.nama }}</span></template>
      </Column>
      <template #empty>
        <div v-if="!list.loading.value" class="kosong muted">Belum ada pergerakan stok untuk barang ini.</div>
      </template>
    </DataTable>
  </div>

  <PenyesuaianDialog v-model:visible="dialog" :sparepart="sp" @saved="list.load" />
</template>

<style scoped>
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.judul {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid var(--p-content-border-color);
}
.judul h2 {
  font-size: 1.05rem;
}
.ket {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.tautan {
  color: var(--accent-text);
  font-weight: 600;
}
.tautan:hover {
  text-decoration: underline;
}
.plus {
  color: var(--p-green-600);
}
.minus {
  color: var(--p-red-500);
}
:deep(.kanan) {
  text-align: right;
  justify-content: flex-end;
}
.kosong {
  padding: 32px 16px;
  text-align: center;
}
</style>
