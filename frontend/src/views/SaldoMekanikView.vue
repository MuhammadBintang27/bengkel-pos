<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { errMsg, get, post } from '@/api/http'
import BarisSaldo from '@/components/BarisSaldo.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useRun } from '@/composables/useRun'
import { rp, tgl, ymd } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const run = useRun()
const id = route.params.id

const d = ref(null)
const error = ref('')
const sampai = ref(new Date())
const saving = ref(false)
const dlg = ref({ cair: false, kasbon: false })
const form = ref({ metode: 'tunai', sumber: 'laci', catatan: '', jumlah: null, keterangan: '' })

async function load() {
  error.value = ''
  try {
    d.value = (await get(`/saldo/${id}`, { sampai: ymd(sampai.value) })).data
  } catch (e) {
    error.value = e.response?.status === 404 ? 'Mekanik tidak ditemukan.' : errMsg(e)
  }
}
onMounted(load)
watch(sampai, (v) => v && load())

const jumlah = (j) => (d.value?.baris || []).filter((b) => b.jenis === j).reduce((s, b) => s + b.jumlah, 0)
const komisi = computed(() => jumlah('komisi'))
const kasbon = computed(() => -jumlah('kasbon'))
const koreksi = computed(() => -jumlah('koreksi'))
const bisa = computed(() => (d.value?.total ?? 0) > 0)

async function cairkan() {
  saving.value = true
  const body = { sampai: ymd(sampai.value), metode: form.value.metode, sumber: form.value.sumber, catatan: form.value.catatan || null }
  let baru
  const ok = await run(async () => { baru = (await post(`/saldo/${id}/cairkan`, body)).data }, 'Pencairan tersimpan')
  saving.value = false
  if (ok) {
    dlg.value.cair = false
    router.push(`/pencairan/${baru.id}`)
  }
}

async function catatKasbon() {
  saving.value = true
  const ok = await run(() => post(`/saldo/${id}/kasbon`, { ...form.value, keterangan: form.value.keterangan || null }), 'Kasbon tercatat')
  saving.value = false
  if (ok) {
    dlg.value.kasbon = false
    form.value.jumlah = null
    form.value.keterangan = ''
    load()
  }
}
</script>

<template>
  <PageHeader :title="d?.mekanik.nama || 'Saldo Mekanik'" subtitle="Rincian komisi yang belum dicairkan.">
    <Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.push('/saldo')" />
    <Button label="Catat Kasbon" icon="pi pi-wallet" severity="secondary" :disabled="!d" @click="dlg.kasbon = true" />
    <Button label="Cairkan" icon="pi pi-check" :disabled="!bisa" @click="dlg.cair = true" />
  </PageHeader>

  <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

  <Skeleton v-else-if="!d" height="320px" border-radius="12px" />

  <div v-else class="panel">
    <div class="atas">
      <label for="sampai">Sampai tanggal</label>
      <DatePicker v-model="sampai" input-id="sampai" date-format="dd/mm/yy" show-icon :manual-input="false" :max-date="new Date()" />
      <span class="muted">Dari {{ d.dari ? tgl(d.dari) : '-' }}</span>
      <span class="muted ka">Kasbon belum terpotong {{ rp(d.kasbon_terpakai) }} dari batas {{ rp(d.mekanik.batas_kasbon) }}</span>
    </div>

    <BarisSaldo :baris="d.baris" />

    <dl class="ringkas">
      <div><dt>Komisi</dt><dd class="num">{{ rp(komisi) }}</dd></div>
      <div><dt>Kasbon</dt><dd class="num">-{{ rp(kasbon) }}</dd></div>
      <div><dt>Koreksi</dt><dd class="num">-{{ rp(koreksi) }}</dd></div>
      <div class="jml"><dt>Dibayar</dt><dd class="num">{{ d.total < 0 ? '-' : '' }}{{ rp(Math.abs(d.total)) }}</dd></div>
    </dl>
    <p v-if="d.baris.length && !bisa" class="muted pad">Total nol atau minus, belum bisa dicairkan. Sisa kasbon terpotong di periode berikutnya.</p>
  </div>

  <Dialog v-model:visible="dlg.cair" modal header="Cairkan Komisi" :style="{ width: '28rem' }" :breakpoints="{ '600px': '94vw' }" :closable="!saving">
    <form class="form-dlg" @submit.prevent="cairkan">
      <p>Cairkan <strong>{{ rp(d?.total) }}</strong> untuk {{ d?.mekanik.nama }} (baris sampai {{ tgl(ymd(sampai)) }}).</p>
      <div class="f"><label for="m1">Metode</label><SelectButton v-model="form.metode" input-id="m1" :options="[{ l: 'Tunai', v: 'tunai' }, { l: 'Transfer', v: 'transfer' }]" option-label="l" option-value="v" :allow-empty="false" /></div>
      <div class="f"><label for="s1">Sumber uang</label><SelectButton v-model="form.sumber" input-id="s1" :options="[{ l: 'Laci kasir', v: 'laci' }, { l: 'Luar laci', v: 'luar' }]" option-label="l" option-value="v" :allow-empty="false" /></div>
      <div class="f"><label for="c1">Catatan</label><InputText id="c1" v-model="form.catatan" maxlength="500" fluid /></div>
      <div class="aksi">
        <Button type="button" label="Kembali" severity="secondary" outlined :disabled="saving" @click="dlg.cair = false" />
        <Button type="submit" label="Cairkan dan Cetak Slip" :loading="saving" />
      </div>
    </form>
  </Dialog>

  <Dialog v-model:visible="dlg.kasbon" modal header="Catat Kasbon" :style="{ width: '28rem' }" :breakpoints="{ '600px': '94vw' }" :closable="!saving">
    <form class="form-dlg" @submit.prevent="catatKasbon">
      <div class="f"><label for="k1">Jumlah <span class="req">*</span></label><InputNumber v-model="form.jumlah" input-id="k1" prefix="Rp" :min="1" fluid required /></div>
      <div class="f"><label for="m2">Metode</label><SelectButton v-model="form.metode" input-id="m2" :options="[{ l: 'Tunai', v: 'tunai' }, { l: 'Transfer', v: 'transfer' }]" option-label="l" option-value="v" :allow-empty="false" /></div>
      <div class="f"><label for="s2">Sumber uang</label><SelectButton v-model="form.sumber" input-id="s2" :options="[{ l: 'Laci kasir', v: 'laci' }, { l: 'Luar laci', v: 'luar' }]" option-label="l" option-value="v" :allow-empty="false" /></div>
      <div class="f"><label for="k2">Keterangan</label><InputText id="k2" v-model="form.keterangan" maxlength="255" fluid /></div>
      <div class="aksi">
        <Button type="button" label="Kembali" severity="secondary" outlined :disabled="saving" @click="dlg.kasbon = false" />
        <Button type="submit" label="Simpan Kasbon" :loading="saving" :disabled="!form.jumlah" />
      </div>
    </form>
  </Dialog>
</template>

<style scoped>
.atas {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--p-content-border-color);
}
.ka {
  margin-left: auto;
}
.ringkas {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 14px 16px;
  max-width: 22rem;
  margin-left: auto;
}
.ringkas div {
  display: flex;
  justify-content: space-between;
  gap: 16px;
}
.ringkas dd {
  margin: 0;
}
.jml {
  font-size: 1.15rem;
  font-weight: 700;
  border-top: 1px solid var(--p-content-border-color);
  padding-top: 6px;
}
.pad {
  padding: 0 16px 14px;
  margin: 0;
  text-align: right;
}
.form-dlg,
.f {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.f {
  gap: 4px;
}
.aksi {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
