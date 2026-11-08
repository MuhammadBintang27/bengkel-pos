<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { errMsg, get, post } from '@/api/http'
import BarisSaldo from '@/components/BarisSaldo.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useRun } from '@/composables/useRun'
import { rp, tgl, tglJam } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const run = useRun()
const id = route.params.id

const c = ref(null)
const error = ref('')
const dlg = ref(false)
const alasan = ref('')
const saving = ref(false)

async function load() {
  try {
    c.value = (await get(`/pencairan/${id}`)).data
  } catch (e) {
    error.value = e.response?.status === 404 ? 'Pencairan tidak ditemukan.' : errMsg(e)
  }
}
onMounted(load)

const slip = () => window.open(`/cetak/slip/${id}?print=1`, '_blank')

async function batal() {
  saving.value = true
  const ok = await run(async () => { c.value = (await post(`/pencairan/${id}/batal`, { alasan: alasan.value })).data }, 'Pencairan dibatalkan')
  saving.value = false
  if (ok) dlg.value = false
}
</script>

<template>
  <PageHeader :title="c?.nomor || 'Pencairan'" subtitle="Rincian pencairan komisi.">
    <Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.push('/pencairan')" />
    <Button label="Cetak Slip" icon="pi pi-print" severity="secondary" :disabled="!c" @click="slip" />
    <Button label="Batalkan" icon="pi pi-times" severity="danger" outlined :disabled="!c || c.batal_at" @click="dlg = true" />
  </PageHeader>

  <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

  <Skeleton v-else-if="!c" height="320px" border-radius="12px" />

  <template v-else>
    <Message v-if="c.batal_at" severity="error" :closable="false">
      <strong>Dibatalkan</strong> oleh {{ c.batal_user?.nama }} pada {{ tglJam(c.batal_at) }}. Alasan: {{ c.batal_alasan }}. Baris di bawah sudah kembali ke saldo mekanik.
    </Message>
    <div class="panel">
      <dl class="meta">
        <div><dt>Mekanik</dt><dd>{{ c.mekanik?.nama }}</dd></div>
        <div><dt>Periode</dt><dd>{{ tgl(c.periode_dari) }} - {{ tgl(c.periode_sampai) }}</dd></div>
        <div><dt>Dibayar</dt><dd>{{ rp(c.dibayar) }} ({{ c.metode }}, {{ c.sumber === 'laci' ? 'laci kasir' : 'luar laci' }})</dd></div>
        <div><dt>Komisi</dt><dd>{{ rp(c.total_komisi) }} · kasbon -{{ rp(c.total_kasbon) }} · koreksi -{{ rp(c.total_koreksi) }}</dd></div>
        <div v-if="c.catatan"><dt>Catatan</dt><dd>{{ c.catatan }}</dd></div>
      </dl>
      <BarisSaldo v-if="!c.batal_at" :baris="c.mutasis" />
    </div>
  </template>

  <Dialog v-model:visible="dlg" modal header="Batalkan Pencairan" :style="{ width: '28rem' }" :breakpoints="{ '600px': '94vw' }" :closable="!saving">
    <form class="form-dlg" @submit.prevent="batal">
      <p>Semua baris kembali menjadi belum dicairkan. Uang yang sudah diserahkan tidak otomatis kembali.</p>
      <label for="alasan">Alasan <span class="req">*</span></label>
      <InputText id="alasan" v-model="alasan" maxlength="255" fluid required />
      <div class="aksi">
        <Button type="button" label="Kembali" severity="secondary" outlined :disabled="saving" @click="dlg = false" />
        <Button type="submit" label="Batalkan Pencairan" severity="danger" :loading="saving" :disabled="alasan.trim().length < 3" />
      </div>
    </form>
  </Dialog>
</template>

<style scoped>
.meta {
  margin: 0;
  padding: 14px 16px;
  display: grid;
  gap: 4px;
}
.meta div {
  display: flex;
  gap: 12px;
}
.meta dt {
  flex: none;
  min-width: 6rem;
  color: var(--p-text-muted-color);
}
.meta dd {
  margin: 0;
}
.form-dlg {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.aksi {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
