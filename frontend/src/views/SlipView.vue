<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { errMsg, get } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { rp, tgl, tglJam } from '@/utils/format'

// Slip komisi: halaman cetak tanpa menu aplikasi, kertas A5.
const route = useRoute()
const auth = useAuthStore()
const c = ref(null)
const error = ref('')
let styleEl

onMounted(async () => {
  styleEl = document.createElement('style')
  styleEl.textContent = '@page { size: A5; margin: 12mm }'
  document.head.appendChild(styleEl)
  try {
    c.value = (await get(`/pencairan/${route.params.id}`)).data
    document.title = `Slip ${c.value.nomor}`
    if (route.query.print) setTimeout(() => window.print(), 400)
  } catch (e) {
    error.value = e.response?.status === 404 ? 'Pencairan tidak ditemukan.' : errMsg(e)
  }
})
onBeforeUnmount(() => styleEl?.remove())

const cetak = () => window.print()
const tutup = () => window.close()
</script>

<template>
  <div class="screen">
    <div class="bar no-print">
      <button type="button" :disabled="!c" @click="cetak">Cetak</button>
      <button type="button" class="alt" @click="tutup">Tutup</button>
    </div>

    <p v-if="error" class="galat">{{ error }}</p>

    <article v-else-if="c" class="paper">
      <header class="kop">
        <strong class="nama">{{ auth.pengaturan.nama_bengkel }}</strong>
        <span>SLIP KOMISI MEKANIK</span>
      </header>
      <div v-if="c.batal_at" class="cap">DIBATALKAN</div>
      <dl class="meta">
        <div><dt>No</dt><dd>{{ c.nomor }}</dd></div>
        <div><dt>Mekanik</dt><dd>{{ c.mekanik?.nama }}</dd></div>
        <div><dt>Periode</dt><dd>{{ tgl(c.periode_dari) }} - {{ tgl(c.periode_sampai) }}</dd></div>
        <div><dt>Dicairkan</dt><dd>{{ tglJam(c.created_at) }} ({{ c.metode }})</dd></div>
      </dl>
      <table>
        <thead><tr><th>Tanggal</th><th>Keterangan</th><th class="r">Jumlah</th></tr></thead>
        <tbody>
          <tr v-for="b in c.mutasis" :key="b.id">
            <td>{{ tgl(b.tanggal) }}</td>
            <td>{{ b.nomor || b.keterangan }}<template v-if="b.plat || b.jasa"> · {{ [b.plat, b.jasa].filter(Boolean).join(' · ') }}</template></td>
            <td class="r">{{ b.jumlah < 0 ? '-' : '' }}{{ rp(Math.abs(b.jumlah)) }}</td>
          </tr>
        </tbody>
      </table>
      <div class="rw"><span>Komisi</span><span>{{ rp(c.total_komisi) }}</span></div>
      <div class="rw"><span>Kasbon</span><span>-{{ rp(c.total_kasbon) }}</span></div>
      <div class="rw"><span>Koreksi</span><span>-{{ rp(c.total_koreksi) }}</span></div>
      <div class="rw total"><span>DITERIMA</span><span>{{ rp(c.dibayar) }}</span></div>
    </article>
  </div>
</template>

<style scoped>
/* Kertas selalu putih-hitam, tidak ikut tema/mode gelap. */
.screen {
  min-height: 100dvh;
  padding: 16px;
  background: #e5e7eb;
  color-scheme: light;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}
.bar {
  display: flex;
  gap: 8px;
}
.bar button {
  border: 0;
  padding: 9px 16px;
  border-radius: 8px;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
  background: #2563eb;
  color: #fff;
}
.bar button.alt {
  background: #fff;
  color: #334155;
}
.bar button:disabled {
  opacity: 0.5;
}
.galat {
  background: #fff;
  padding: 16px 24px;
  border-radius: 8px;
  color: #b91c1c;
}
.paper {
  width: 148mm;
  background: #fff;
  color: #000;
  padding: 12mm;
  box-shadow: 0 4px 24px rgb(0 0 0 / 0.18);
  font-size: 13px;
  line-height: 1.4;
}
.kop {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 8px;
}
.nama {
  font-size: 1.3em;
}
.cap {
  margin: 6px 0;
  padding: 3px;
  border: 2px solid #000;
  text-align: center;
  font-weight: 700;
}
.meta {
  margin: 8px 0;
}
.meta div {
  display: flex;
  gap: 8px;
}
.meta dt {
  min-width: 22mm;
}
.meta dd {
  margin: 0;
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 8px;
}
th,
td {
  text-align: left;
  padding: 3px 4px;
  border-bottom: 1px dashed #999;
}
.r {
  text-align: right;
  white-space: nowrap;
}
.rw {
  display: flex;
  justify-content: space-between;
}
.total {
  margin-top: 4px;
  padding-top: 4px;
  border-top: 2px solid #000;
  font-size: 1.2em;
  font-weight: 700;
}

@media print {
  .screen {
    padding: 0;
    background: #fff;
    display: block;
  }
  .no-print {
    display: none !important;
  }
  .paper {
    width: 100%;
    padding: 0;
    box-shadow: none;
  }
}
</style>
