<script setup>
import { computed, onMounted, ref } from 'vue'
import { get } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatCard from '@/components/StatCard.vue'
import { useAuthStore } from '@/stores/auth'
import { rp } from '@/utils/format'

const auth = useAuthStore()
const d = ref(null)
const gagal = ref(false)

const sapaan = computed(() => {
  const h = new Date().getHours()
  return h < 11 ? 'Selamat pagi' : h < 15 ? 'Selamat siang' : h < 19 ? 'Selamat sore' : 'Selamat malam'
})
const tanggal = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

const AKSI = [
  { label: 'Papan Servis', desc: 'Motor di bengkel dan motor masuk', icon: 'servis', to: '/servis' },
  { label: 'Kasir Sparepart', desc: 'Jual sparepart tanpa servis', icon: 'jual', to: '/kasir' },
  { label: 'Terima Barang', desc: 'Barang datang dari supplier', icon: 'truk', to: '/pembelian/baru' },
  { label: 'Riwayat Servis', desc: 'Cari riwayat per plat nomor', icon: 'motor', to: '/riwayat' },
]

async function load() {
  gagal.value = false
  try {
    d.value = (await get('/dashboard')).data
  } catch {
    gagal.value = true
  }
}
onMounted(load)
</script>

<template>
  <PageHeader :title="`${sapaan}, ${auth.user.nama.split(' ')[0]}`" :subtitle="tanggal" />

  <section class="aksi">
    <RouterLink v-for="a in AKSI" :key="a.to" :to="a.to" class="panel aksi-item">
      <span class="aksi-ico"><BIcon :name="a.icon" :size="24" /></span>
      <span>
        <strong>{{ a.label }}</strong>
        <small class="muted">{{ a.desc }}</small>
      </span>
      <i class="pi pi-angle-right muted go" />
    </RouterLink>
  </section>

  <Message v-if="gagal" severity="error" class="mb-4">
    Ringkasan tidak bisa dimuat. <a href="#" class="underline" @click.prevent="load">Coba lagi</a>
  </Message>

  <h2 class="section-title">Ringkasan hari ini</h2>
  <section class="stats">
    <template v-if="d">
      <StatCard icon="kas" label="Omzet hari ini" :value="rp(d.omzet_hari_ini)" :hint="`${d.transaksi_hari_ini} transaksi lunas`" tone="green" to="/laporan/kas" />
      <StatCard v-if="auth.isOwner" icon="laporan" label="Laba kotor hari ini" :value="rp(d.laba_kotor_hari_ini)" hint="Setelah HPP dan komisi" />
      <StatCard icon="servis" label="Transaksi terbuka" :value="d.transaksi_terbuka" hint="Antri, dikerjakan, atau selesai" tone="orange" to="/transaksi" />
      <StatCard icon="stok" label="Stok menipis" :value="d.stok_menipis" hint="Di bawah stok minimum" :tone="d.stok_menipis ? 'red' : 'primary'" to="/stok?status=menipis" />
      <StatCard icon="truk" label="Tempo supplier" :value="d.tempo_jatuh" hint="Jatuh tempo ≤ 7 hari" :tone="d.tempo_jatuh ? 'red' : 'primary'" to="/pembelian?tempo=1" />
      <StatCard v-if="auth.isOwner" icon="mekanik" label="Saldo komisi mekanik" :value="rp(d.saldo_mekanik)" hint="Belum dicairkan" to="/saldo" />
    </template>
    <template v-else-if="!gagal">
      <Skeleton v-for="i in 4" :key="i" height="96px" border-radius="12px" />
    </template>
  </section>
</template>

<style scoped>
.aksi {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 12px;
  margin-bottom: 28px;
}
.aksi-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  transition: border-color 0.15s, transform 0.15s;
}
.aksi-item:hover {
  border-color: var(--p-primary-color);
  transform: translateY(-1px);
}
.aksi-item strong,
.aksi-item small {
  display: block;
}
.aksi-ico {
  width: 44px;
  height: 44px;
  flex: none;
  border-radius: 10px;
  display: grid;
  place-items: center;
  font-size: 1.2rem;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}
.go {
  margin-left: auto;
}
.section-title {
  font-size: 1rem;
  margin-bottom: 12px;
}
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 12px;
}
</style>
