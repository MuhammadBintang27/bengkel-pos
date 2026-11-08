<script setup>
import { onMounted, ref, watch } from 'vue'
import { errMsg, get } from '@/api/http'
import PageHeader from '@/components/PageHeader.vue'
import StatCard from '@/components/StatCard.vue'
import { useAuthStore } from '@/stores/auth'
import { METODE, rp, tgl, ymd } from '@/utils/format'

const auth = useAuthStore()
const d = ref(null)
const error = ref('')
const tanggal = ref(new Date())

const uang = (n) => (n < 0 ? '-' : '') + rp(Math.abs(n))
const IKON_KELUAR = { kasbon: 'pi pi-wallet', pencairan: 'pi pi-users', supplier: 'pi pi-truck' }
const hariIni = () => d.value?.tanggal === ymd(new Date())

async function load() {
  error.value = ''
  try {
    d.value = (await get('/laporan/kas', { tanggal: ymd(tanggal.value) })).data
  } catch (e) {
    error.value = errMsg(e)
  }
}
onMounted(load)
watch(tanggal, (v) => v && load())
</script>

<template>
  <PageHeader title="Rekap Kas" :subtitle="d ? `Uang yang masuk dan keluar pada ${hariIni() ? 'hari ini' : tgl(d.tanggal)}.` : 'Uang yang masuk dan keluar per hari.'">
    <DatePicker v-if="auth.isOwner" v-model="tanggal" date-format="dd/mm/yy" show-icon :manual-input="false" :max-date="new Date()" aria-label="Pilih tanggal" />
  </PageHeader>

  <Message v-if="error" severity="error" :closable="false">{{ error }} <a href="#" class="underline" @click.prevent="load">Coba lagi</a></Message>
  <div v-else-if="!d" class="stats"><Skeleton v-for="i in 3" :key="i" height="96px" border-radius="12px" /></div>

  <template v-else>
    <section class="stats">
      <StatCard icon="kas" label="Uang masuk" :value="rp(d.total_masuk)" hint="Dari semua pembayaran pelanggan" tone="green" />
      <StatCard icon="laporan" label="Uang keluar dari laci" :value="rp(d.total_keluar)" hint="Kasbon, gaji komisi, bayar supplier" tone="orange" />
      <StatCard icon="kas" label="Tunai yang seharusnya di laci" :value="uang(d.selisih_laci)" :hint="`Tunai masuk ${rp(d.tunai_masuk)} dikurangi uang keluar`" :tone="d.selisih_laci < 0 ? 'red' : 'primary'" />
    </section>

    <section class="dua">
      <div class="panel kotak">
        <h3>Uang masuk, menurut cara bayar</h3>
        <ul v-if="d.masuk.length" class="daftar">
          <li v-for="m in d.masuk" :key="m.metode">
            <i :class="METODE[m.metode]?.icon" class="ikon" />
            <span class="teks">{{ METODE[m.metode]?.label || m.metode }}<small class="muted">{{ m.jumlah }} transaksi</small></span>
            <strong class="num">{{ rp(m.total) }}</strong>
          </li>
        </ul>
        <p v-else class="kosong muted">Belum ada pembayaran pada tanggal ini.</p>
      </div>

      <div class="panel kotak">
        <h3>Uang keluar dari laci</h3>
        <ul v-if="d.keluar.length" class="daftar">
          <li v-for="(k, i) in d.keluar" :key="i">
            <i :class="IKON_KELUAR[k.jenis]" class="ikon" />
            <span class="teks">{{ k.keterangan }}</span>
            <strong class="num">{{ rp(k.jumlah) }}</strong>
          </li>
        </ul>
        <p v-else class="kosong muted">Tidak ada uang keluar dari laci.</p>
      </div>
    </section>

    <p class="catatan muted">Uang keluar yang diambil dari luar laci (misalnya transfer) tidak ikut dihitung di sini. Hitungan ini belum termasuk uang kembalian awal di laci.</p>
  </template>
</template>

<style scoped>
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}
.dua {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 16px;
}
.kotak {
  padding: 20px;
}
.kotak h3 {
  margin: 0 0 14px;
  font-size: 1rem;
}
.daftar {
  display: grid;
  gap: 14px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.daftar li {
  display: flex;
  align-items: center;
  gap: 12px;
}
.ikon {
  width: 36px;
  height: 36px;
  flex: none;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
  color: var(--p-primary-color);
}
.teks {
  flex: 1;
  min-width: 0;
}
.teks small {
  display: block;
}
.kosong {
  margin: 0;
  padding: 16px 0;
  text-align: center;
}
.catatan {
  margin: 16px 0 0;
  font-size: 0.85rem;
}
</style>
