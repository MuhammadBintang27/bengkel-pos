<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { errMsg, get, post } from '@/api/http'
import PageHeader from '@/components/PageHeader.vue'
import { useRun } from '@/composables/useRun'
import { qty, STATUS, tglJam } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const run = useRun()
const id = route.params.id

const t = ref(null)
const error = ref('')
const saving = ref(false)

async function load() {
  try {
    t.value = (await get(`/mekanik/pekerjaan/${id}`)).data
  } catch (e) {
    error.value = e.response?.status === 404 ? 'Pekerjaan tidak ditemukan.' : errMsg(e)
  }
}
onMounted(load)

// Status hanya maju: antri → dikerjakan → selesai. Setelah selesai, kasir yang menagih.
const berikut = computed(() => ({ antri: { status: 'dikerjakan', label: 'Mulai Kerjakan' }, dikerjakan: { status: 'selesai', label: 'Tandai Selesai' } })[t.value?.status])

async function maju() {
  saving.value = true
  await run(async () => {
    await post(`/mekanik/pekerjaan/${id}/status`, { status: berikut.value.status })
    await load()
  }, 'Status diperbarui')
  saving.value = false
}
</script>

<template>
  <PageHeader :title="t?.plat || 'Pekerjaan'" :subtitle="t?.motor || 'Detail pekerjaan.'">
    <Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.push('/mekanik/pekerjaan')" />
  </PageHeader>

  <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
  <div v-else-if="!t" class="susun"><Skeleton height="140px" border-radius="12px" /><Skeleton height="200px" border-radius="12px" /></div>

  <div v-else class="susun">
    <div class="panel kotak">
      <div class="baris">
        <span class="muted">{{ t.nomor }} · masuk {{ tglJam(t.masuk_at) }}</span>
        <Tag :value="STATUS[t.status].label" :severity="STATUS[t.status].severity" />
      </div>
      <p v-if="t.keluhan" class="isi"><span class="muted">Keluhan</span><br />{{ t.keluhan }}</p>
      <p v-if="t.km != null" class="isi"><span class="muted">KM</span><br />{{ qty(t.km) }}</p>
      <Button v-if="berikut" :label="berikut.label" icon="pi pi-check" size="large" fluid :loading="saving" @click="maju" />
      <small v-else-if="t.status === 'selesai'" class="muted">Pekerjaan selesai. Menunggu pembayaran di kasir.</small>
    </div>

    <div class="panel kotak">
      <h3 class="jud">Jasa</h3>
      <ul class="daftar">
        <li v-for="j in t.jasas" :key="j.id">
          <span>{{ j.nama }}</span>
          <small class="muted">{{ j.saya ? 'Anda' : j.mekanik }}</small>
        </li>
      </ul>
    </div>

    <div v-if="t.spareparts.length" class="panel kotak">
      <h3 class="jud">Sparepart</h3>
      <ul class="daftar">
        <li v-for="s in t.spareparts" :key="s.id">
          <span>{{ s.nama }}</span>
          <small class="muted">{{ qty(s.qty) }} {{ s.satuan }}</small>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.susun {
  display: grid;
  gap: 16px;
}
.kotak {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 20px;
}
.baris {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.isi {
  margin: 0;
}
.jud {
  margin: 0;
  font-size: 1rem;
}
.daftar {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.daftar li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
</style>
