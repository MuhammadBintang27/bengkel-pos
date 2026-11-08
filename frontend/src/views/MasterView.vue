<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { del, errMsg, fieldErrors, get, post, put } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import TableSkeleton from '@/components/TableSkeleton.vue'
import FormField from '@/components/FormField.vue'
import PageHeader from '@/components/PageHeader.vue'
import { usePagedList } from '@/composables/usePagedList'
import { qty, rp } from '@/utils/format'

// Kategori dan satuan dikelompokkan dalam satu halaman dengan tab.
const KELOMPOK = [
  { jenis: 'kategori-sparepart', label: 'Kategori Sparepart' },
  { jenis: 'kategori-jasa', label: 'Kategori Jasa' },
  { jenis: 'satuan', label: 'Satuan' },
]

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()

const jenis = computed(() => route.params.jenis)
const meta = ref(null)
const metaError = ref('')
const aktif = ref('semua') // 'semua' bukan '' karena SelectButton menganggap '' sebagai kosong

const list = usePagedList(() => `/master/${jenis.value}`)

const kolom = computed(() => (meta.value?.fields || []).filter((f) => f.table))
const extra = computed(() => meta.value?.extraColumns || [])
const kerangka = computed(() => !meta.value || (list.loading.value && !list.rows.value.length))
const bisaSort = (f) => !f.show && f.type !== 'password'

async function muat() {
  meta.value = null
  metaError.value = ''
  aktif.value = 'semua'
  Object.assign(list.state, { page: 1, q: '', sort: '', order: 'asc', aktif: '' })
  list.rows.value = []
  try {
    meta.value = (await get(`/master/${jenis.value}/meta`)).data
    await list.load()
  } catch (e) {
    metaError.value = e.response?.status === 404 ? 'Halaman ini tidak ada.' : errMsg(e)
  }
}
watch(jenis, muat, { immediate: true })
watch(aktif, (v) => {
  list.state.aktif = v === 'semua' ? '' : v
  list.reset()
})

const path = (obj, p) => p.split('.').reduce((o, k) => o?.[k], obj)
const label = (f, v) => f.options?.find((o) => String(o.value) === String(v))?.label ?? v

// --- Form tambah / ubah ---
const dialog = ref(false)
const editing = ref(null)
const form = reactive({})
const errors = ref({})
const saving = ref(false)

const tampil = (f) => !f.when || Object.entries(f.when).every(([k, val]) => form[k] === val)

function buka(row = null) {
  editing.value = row
  errors.value = {}
  for (const k of Object.keys(form)) delete form[k]
  for (const f of meta.value.fields) {
    form[f.key] = row ? (row[f.key] ?? null) : (f.default ?? (f.type === 'switch' ? false : null))
    if (f.type === 'password') form[f.key] = null
  }
  dialog.value = true
}

async function simpan() {
  saving.value = true
  errors.value = {}
  try {
    const body = Object.fromEntries(meta.value.fields.filter(tampil).map((f) => [f.key, form[f.key]]))
    const res = editing.value ? await put(`/master/${jenis.value}/${editing.value.id}`, body) : await post(`/master/${jenis.value}`, body)
    toast.add({ severity: 'success', summary: 'Berhasil', detail: res.message, life: 3000 })
    dialog.value = false
    await list.refresh()
  } catch (e) {
    errors.value = fieldErrors(e)
    if (!Object.keys(errors.value).length) toast.add({ severity: 'error', summary: 'Gagal menyimpan', detail: errMsg(e), life: 5000 })
  } finally {
    saving.value = false
  }
}

function hapus(row) {
  const nama = row.nama || row.plat || row.username
  confirm.require({
    header: `Hapus ${meta.value.label.toLowerCase()}?`,
    message: `"${nama}" akan dihapus. Riwayat transaksi yang sudah ada tidak berubah.`,
    icon: 'pi pi-trash',
    acceptProps: { label: 'Hapus', severity: 'danger' },
    rejectProps: { label: 'Batal', severity: 'secondary', outlined: true },
    accept: async () => {
      try {
        const res = await del(`/master/${jenis.value}/${row.id}`)
        toast.add({ severity: 'success', summary: 'Dihapus', detail: res.message, life: 3000 })
        await list.refresh()
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Tidak bisa dihapus', detail: errMsg(e), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <PageHeader :title="meta?.label || 'Master Data'" :subtitle="meta?.desc">
    <Button v-if="meta" :label="`Tambah ${meta.label}`" icon="pi pi-plus" @click="buka()" />
  </PageHeader>

  <SelectButton
    v-if="KELOMPOK.some((k) => k.jenis === jenis)"
    :model-value="jenis"
    :options="KELOMPOK"
    option-label="label"
    option-value="jenis"
    :allow-empty="false"
    class="tabs"
    @update:model-value="router.push(`/master/${$event}`)"
  />

  <Message v-if="metaError" severity="error" :closable="false">{{ metaError }}</Message>

  <div v-else class="panel">
    <div class="toolbar">
      <IconField class="cari">
        <InputIcon class="pi pi-search" />
        <InputText v-model="list.state.q" placeholder="Cari..." fluid aria-label="Cari" @input="list.search" />
      </IconField>
      <SelectButton
        v-if="meta?.hasAktif"
        v-model="aktif"
        :options="[{ l: 'Semua', v: 'semua' }, { l: 'Aktif', v: '1' }, { l: 'Nonaktif', v: '0' }]"
        option-label="l"
        option-value="v"
        :allow-empty="false"
      />
      <span class="muted total">{{ list.total.value }} data</span>
    </div>

    <Message v-if="list.error.value" severity="error" :closable="false" class="m-3">
      {{ list.error.value }} <a href="#" class="underline" @click.prevent="list.load">Coba lagi</a>
    </Message>

    <TableSkeleton v-if="kerangka && !metaError" :cols="Math.max(kolom.length + extra.length, 4)" />
    <DataTable
      v-else
      :value="list.rows.value"
      lazy
      paginator
      :rows="list.state.per_page"
      :rows-per-page-options="[15, 30, 50]"
      :total-records="list.total.value"
      :loading="list.loading.value"
      :sort-field="list.state.sort || null"
      :sort-order="list.state.sort ? (list.state.order === 'desc' ? -1 : 1) : null"
      data-key="id"
      row-hover
      scrollable
      paginator-template="RowsPerPageDropdown PrevPageLink PageLinks NextPageLink CurrentPageReport"
      current-page-report-template="{first}-{last} dari {totalRecords}"
      @page="list.onPage"
      @sort="list.onSort"
    >
      <Column v-for="f in kolom" :key="f.key" :field="f.key" :header="f.label" :sortable="bisaSort(f)">
        <template #body="{ data }">
          <Tag v-if="f.type === 'switch'" :value="data[f.key] ? 'Aktif' : 'Nonaktif'" :severity="data[f.key] ? 'success' : 'secondary'" />
          <span v-else-if="f.type === 'money'" class="num">{{ rp(data[f.key]) }}</span>
          <span v-else-if="f.type === 'percent'" class="num">{{ qty(data[f.key]) }}%</span>
          <span v-else-if="f.show">{{ path(data, f.show) ?? '-' }}</span>
          <Tag v-else-if="f.type === 'select' && f.options" :value="label(f, data[f.key])" severity="info" />
          <strong v-else-if="f.type === 'plat'" class="plat">{{ data[f.key] }}</strong>
          <span v-else>{{ data[f.key] ?? '-' }}</span>
        </template>
      </Column>
      <Column v-for="c in extra" :key="c.key" :field="c.key" :header="c.label" :sortable="c.key.endsWith('_count')">
        <template #body="{ data }">
          <RouterLink v-if="jenis === 'rak' && c.key === 'spareparts_count'" :to="`/sparepart?rak=${data.id}`" class="tautan" :aria-label="`Lihat ${data.spareparts_count} barang di rak ${data.nama}`">
            {{ data.spareparts_count }} barang <i class="pi pi-arrow-right" />
          </RouterLink>
          <template v-else>{{ path(data, c.key) ?? '-' }}</template>
        </template>
      </Column>
      <Column header="" style="width: 7rem" align-frozen="right" frozen>
        <template #body="{ data }">
          <div class="aksi">
            <Button icon="pi pi-pencil" text rounded severity="secondary" aria-label="Ubah" @click="buka(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus" @click="hapus(data)" />
          </div>
        </template>
      </Column>

      <template #empty>
        <div v-if="!list.loading.value && meta && !list.error.value" class="kosong">
          <BIcon :name="meta.icon" :size="40" />
          <strong>{{ list.state.q ? 'Tidak ada yang cocok' : `Belum ada data ${meta.label.toLowerCase()}` }}</strong>
          <span class="muted">{{ list.state.q ? 'Coba kata kunci lain.' : 'Klik tombol Tambah untuk mulai mengisi.' }}</span>
          <Button v-if="!list.state.q" :label="`Tambah ${meta.label}`" icon="pi pi-plus" class="mt-2" @click="buka()" />
        </div>
      </template>
    </DataTable>
  </div>

  <Dialog
    v-model:visible="dialog"
    modal
    :header="meta ? `${editing ? 'Ubah' : 'Tambah'} ${meta.label}` : ''"
    :style="{ width: '40rem' }"
    :breakpoints="{ '700px': '94vw' }"
    :closable="!saving"
  >
    <form v-if="meta" class="grid-form" @submit.prevent="simpan">
      <template v-for="f in meta.fields" :key="f.key">
        <FormField v-if="tampil(f)" v-model="form[f.key]" :field="f" :error="errors[f.key]" />
      </template>
      <div class="actions">
        <Button type="button" label="Batal" severity="secondary" outlined :disabled="saving" @click="dialog = false" />
        <Button type="submit" label="Simpan" icon="pi pi-check" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>

<style scoped>
.tabs {
  margin-bottom: 16px;
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--p-content-border-color);
}
.cari {
  flex: 1 1 240px;
  max-width: 360px;
}
.total {
  margin-left: auto;
}
.aksi {
  display: flex;
  justify-content: flex-end;
}
.tautan {
  color: var(--accent-text);
  font-weight: 600;
}
.tautan:hover {
  text-decoration: underline;
}
.tautan .pi {
  font-size: 0.7rem;
}
.plat {
  letter-spacing: 0.06em;
}
.kosong {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 40px 16px;
  text-align: center;
  color: var(--p-text-muted-color);
}
.kosong strong {
  color: var(--p-text-color);
  font-size: 1.05rem;
}
.grid-form {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}
.actions {
  grid-column: 1 / -1;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 8px;
}
</style>
