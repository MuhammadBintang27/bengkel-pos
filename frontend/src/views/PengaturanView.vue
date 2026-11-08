<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { errMsg, fieldErrors, get, put } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import CardSkeleton from '@/components/CardSkeleton.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatCard from '@/components/StatCard.vue'
import TunasLogo from '@/components/TunasLogo.vue'
import { applyTheme, isHex, MODES, PRIMARY_COLORS, THEMES } from '@/composables/useAppTheme'
import { useAuthStore } from '@/stores/auth'

const KEYS = ['nama_bengkel', 'alamat', 'telepon', 'logo', 'footer_nota', 'ukuran_nota', 'tema', 'warna', 'mode']
const UKURAN = [
  { label: 'Thermal 58 mm', value: '58' },
  { label: 'Thermal 80 mm', value: '80' },
  { label: 'A5', value: 'A5' },
]
const NAMA_WARNA = {
  amber: 'Kuning tua', orange: 'Oranye', red: 'Merah', rose: 'Mawar', pink: 'Merah muda', fuchsia: 'Fuchsia',
  purple: 'Ungu', violet: 'Violet', indigo: 'Nila', blue: 'Biru', sky: 'Biru langit', cyan: 'Sian',
  teal: 'Hijau toska', emerald: 'Zamrud', green: 'Hijau', lime: 'Hijau limau', yellow: 'Kuning', slate: 'Abu-abu',
}

const auth = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const tersimpan = ref(null)
const form = reactive({})
const errors = ref({})
const saving = ref(false)

const dirty = computed(() => tersimpan.value && KEYS.some((k) => (form[k] ?? '') !== (tersimpan.value[k] ?? '')))
const customHex = computed({
  get: () => (isHex(form.warna) ? form.warna : '#b45309'),
  set: (v) => (form.warna = v),
})

onMounted(async () => {
  try {
    const res = await get('/pengaturan')
    tersimpan.value = res.data
    Object.assign(form, res.data)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Gagal memuat', detail: errMsg(e), life: 5000 })
  }
})

// Pratinjau langsung tanpa menyimpan.
watch(
  () => [form.tema, form.warna, form.mode],
  () => tersimpan.value && applyTheme({ ...form }, { persist: false }),
)

const kembalikanTampilan = () => tersimpan.value && applyTheme(tersimpan.value, { persist: false })
onBeforeUnmount(kembalikanTampilan)

onBeforeRouteLeave(() => {
  if (!dirty.value) return true
  return new Promise((resolve) =>
    confirm.require({
      header: 'Perubahan belum disimpan',
      message: 'Tinggalkan halaman dan buang perubahan?',
      icon: 'pi pi-exclamation-triangle',
      acceptProps: { label: 'Buang perubahan', severity: 'danger' },
      rejectProps: { label: 'Tetap di sini', severity: 'secondary', outlined: true },
      accept: () => resolve(true),
      reject: () => resolve(false),
      onHide: () => resolve(false),
    }),
  )
})

function batal() {
  Object.assign(form, tersimpan.value)
  errors.value = {}
}

async function simpan() {
  saving.value = true
  errors.value = {}
  try {
    const res = await put('/pengaturan', { ...form })
    tersimpan.value = res.data
    Object.assign(form, res.data)
    auth.pengaturan = res.data
    applyTheme(res.data)
    toast.add({ severity: 'success', summary: 'Tersimpan', detail: 'Semua pengguna akan melihat tampilan baru.', life: 3000 })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: 'Gagal menyimpan', detail: Object.values(errors.value)[0] || errMsg(e), life: 5000 })
  } finally {
    saving.value = false
  }
}

// Logo diperkecil di browser (maks 256 px) supaya ringan disimpan di database.
const fileInput = ref()
function pilihLogo(e) {
  const file = e.target.files[0]
  e.target.value = ''
  if (!file) return
  if (!file.type.startsWith('image/')) {
    return toast.add({ severity: 'warn', summary: 'Bukan gambar', detail: 'Pilih file PNG, JPG, atau WEBP.', life: 4000 })
  }
  const img = new Image()
  img.onload = () => {
    const s = Math.min(1, 256 / Math.max(img.width, img.height))
    const c = document.createElement('canvas')
    c.width = Math.round(img.width * s)
    c.height = Math.round(img.height * s)
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height)
    form.logo = c.toDataURL('image/png')
    URL.revokeObjectURL(img.src)
  }
  img.onerror = () => toast.add({ severity: 'warn', summary: 'Gambar rusak', detail: 'File tidak bisa dibaca.', life: 4000 })
  img.src = URL.createObjectURL(file)
}
</script>

<template>
  <PageHeader title="Pengaturan" subtitle="Tampilan aplikasi, identitas bengkel, dan nota. Berlaku untuk semua pengguna." />

  <CardSkeleton v-if="!tersimpan" :cards="2" :fields="3" />

  <template v-else>
    <div class="grid-2">
      <!-- Tampilan -->
      <section class="panel card">
        <header class="card-head">
          <span class="card-ico"><BIcon name="pengaturan" /></span>
          <div>
            <h2>Tampilan</h2>
            <p class="muted">Perubahan langsung terlihat sebagai pratinjau.</p>
          </div>
        </header>

        <div class="field">
          <label>Warna utama</label>
          <div class="swatches" role="radiogroup" aria-label="Warna utama">
            <button
              v-for="c in PRIMARY_COLORS"
              :key="c.name"
              v-tooltip.top="NAMA_WARNA[c.name]"
              type="button"
              role="radio"
              class="swatch"
              :class="{ on: form.warna === c.name }"
              :aria-checked="form.warna === c.name"
              :aria-label="NAMA_WARNA[c.name]"
              :style="{ background: c.hex }"
              @click="form.warna = c.name"
            >
              <i v-if="form.warna === c.name" class="pi pi-check" />
            </button>
            <label v-tooltip.top="'Warna brand sendiri'" class="swatch custom" :class="{ on: isHex(form.warna) }" :style="isHex(form.warna) ? { background: form.warna } : null">
              <i :class="isHex(form.warna) ? 'pi pi-check' : 'pi pi-plus'" />
              <input v-model="customHex" type="color" aria-label="Pilih warna brand sendiri" />
            </label>
          </div>
          <small class="hint">
            <template v-if="isHex(form.warna)">Warna brand: <strong>{{ form.warna.toUpperCase() }}</strong> — klik kotak terakhir untuk mengubah.</template>
            <template v-else>Dipilih: <strong>{{ NAMA_WARNA[form.warna] }}</strong>. Kotak terakhir untuk warna brand sendiri.</template>
          </small>
        </div>

        <div class="field">
          <label>Mode</label>
          <SelectButton v-model="form.mode" :options="MODES" option-label="label" option-value="value" :allow-empty="false" fluid>
            <template #option="{ option }"><i :class="option.icon" /> {{ option.label }}</template>
          </SelectButton>
          <small class="hint">Otomatis mengikuti pengaturan terang/gelap di perangkat masing-masing.</small>
        </div>

        <div class="field">
          <label>Gaya komponen</label>
          <div class="themes" role="radiogroup" aria-label="Gaya komponen">
            <button
              v-for="t in THEMES"
              :key="t.id"
              type="button"
              role="radio"
              class="theme"
              :class="{ on: form.tema === t.id }"
              :aria-checked="form.tema === t.id"
              @click="form.tema = t.id"
            >
              <span class="theme-demo" :data-t="t.id"><span /><span /></span>
              {{ t.label }}
            </button>
          </div>
          <small class="hint">Mengubah bentuk tombol, input, dan sudut komponen.</small>
        </div>
      </section>

      <!-- Pratinjau -->
      <section class="panel card">
        <header class="card-head">
          <span class="card-ico"><BIcon name="dashboard" /></span>
          <div>
            <h2>Pratinjau</h2>
            <p class="muted">Contoh tampilan dengan pilihan saat ini.</p>
          </div>
        </header>
        <div class="preview">
          <div class="pv-brand">
            <img v-if="form.logo" :src="form.logo" alt="" class="pv-logo" />
            <TunasLogo v-else :size="40" />
            <strong>{{ form.nama_bengkel || 'Nama bengkel' }}</strong>
          </div>
          <StatCard icon="kas" label="Omzet hari ini" value="Rp1.250.000" hint="12 transaksi lunas" tone="green" />
          <div class="flex flex-wrap gap-2">
            <Button label="Simpan" icon="pi pi-check" />
            <Button label="Batal" severity="secondary" outlined />
            <Button label="Detail" text />
          </div>
          <div class="flex flex-wrap gap-2">
            <Tag value="Antri" severity="secondary" />
            <Tag value="Dikerjakan" severity="info" />
            <Tag value="Selesai" severity="warn" />
            <Tag value="Lunas" severity="success" />
            <Tag value="Batal" severity="danger" />
          </div>
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText placeholder="Cari plat nomor…" fluid />
          </IconField>
        </div>
      </section>

      <!-- Identitas -->
      <section class="panel card">
        <header class="card-head">
          <span class="card-ico"><BIcon name="master" /></span>
          <div>
            <h2>Identitas Bengkel</h2>
            <p class="muted">Tampil di menu, halaman login, dan nota.</p>
          </div>
        </header>

        <div class="field">
          <label for="nama">Nama bengkel <span class="req">*</span></label>
          <InputText id="nama" v-model="form.nama_bengkel" maxlength="80" :invalid="!!errors.nama_bengkel" fluid />
          <small v-if="errors.nama_bengkel" class="err">{{ errors.nama_bengkel }}</small>
        </div>
        <div class="field">
          <label>Logo</label>
          <div class="logo-row">
            <img v-if="form.logo" :src="form.logo" alt="Logo bengkel" class="logo-img" />
            <TunasLogo v-else :size="64" />
            <div class="flex flex-col gap-2">
              <div class="flex gap-2">
                <Button :label="form.logo ? 'Ganti logo' : 'Unggah logo'" icon="pi pi-upload" outlined size="small" @click="fileInput.click()" />
                <Button v-if="form.logo" label="Pakai logo bawaan" severity="secondary" text size="small" @click="form.logo = ''" />
              </div>
              <small class="hint">PNG, JPG, atau WEBP. Otomatis diperkecil.</small>
            </div>
            <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" hidden @change="pilihLogo" />
          </div>
          <small v-if="errors.logo" class="err">{{ errors.logo }}</small>
        </div>
        <div class="field">
          <label for="alamat">Alamat</label>
          <Textarea id="alamat" v-model="form.alamat" rows="2" maxlength="255" auto-resize fluid />
        </div>
        <div class="field">
          <label for="telp">Telepon / WhatsApp</label>
          <InputText id="telp" v-model="form.telepon" maxlength="30" inputmode="tel" fluid />
        </div>
      </section>

      <!-- Nota -->
      <section class="panel card">
        <header class="card-head">
          <span class="card-ico"><BIcon name="nota" /></span>
          <div>
            <h2>Nota</h2>
            <p class="muted">Dicetak lewat browser.</p>
          </div>
        </header>
        <div class="field">
          <label>Ukuran kertas</label>
          <SelectButton v-model="form.ukuran_nota" :options="UKURAN" option-label="label" option-value="value" :allow-empty="false" fluid />
          <small class="hint">58 mm untuk printer thermal kecil, A5 untuk printer biasa.</small>
        </div>
        <div class="field">
          <label for="footer">Catatan kaki nota</label>
          <InputText id="footer" v-model="form.footer_nota" maxlength="255" placeholder="Terima kasih atas kepercayaan Anda" fluid />
          <small class="hint">Contoh: garansi servis 7 hari, jam buka, atau nomor rekening.</small>
        </div>
      </section>
    </div>

    <Transition name="bar">
      <div v-if="dirty" class="savebar panel" role="region" aria-label="Perubahan belum disimpan">
        <span><i class="pi pi-info-circle" /> Ada perubahan yang belum disimpan</span>
        <div class="flex gap-2">
          <Button label="Batal" severity="secondary" outlined :disabled="saving" @click="batal" />
          <Button label="Simpan Perubahan" icon="pi pi-check" :loading="saving" @click="simpan" />
        </div>
      </div>
    </Transition>
  </template>
</template>

<style scoped>
.grid-2 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
  gap: 16px;
  padding-bottom: 88px;
}
.card {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.card-head {
  display: flex;
  align-items: center;
  gap: 12px;
}
.card-head h2 {
  font-size: 1.1rem;
}
.card-head p {
  margin: 2px 0 0;
  font-size: 0.9rem;
}
.card-ico {
  width: 40px;
  height: 40px;
  flex: none;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
  color: var(--accent-text);
}

.swatches {
  display: grid;
  grid-template-columns: repeat(auto-fill, 36px);
  gap: 10px;
}
.swatch {
  position: relative;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 0;
  cursor: pointer;
  display: grid;
  place-items: center;
  color: #fff;
  font-size: 0.85rem;
  box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.08);
  transition: transform 0.12s;
}
.swatch:hover {
  transform: scale(1.1);
}
.swatch.on {
  outline: 2px solid var(--p-text-color);
  outline-offset: 2px;
}
.swatch:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}
.swatch.custom {
  background: conic-gradient(#ef4444, #eab308, #22c55e, #06b6d4, #3b82f6, #a855f7, #ef4444);
}
.swatch.custom input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.themes {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}
.theme {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 12px 6px;
  border: 1px solid var(--p-content-border-color);
  border-radius: 10px;
  background: var(--p-content-background);
  color: var(--p-text-color);
  font: inherit;
  font-weight: 500;
  cursor: pointer;
}
.theme:hover {
  border-color: var(--p-primary-color);
}
.theme.on {
  border-color: var(--p-primary-color);
  box-shadow: 0 0 0 1px var(--p-primary-color);
  color: var(--accent-text);
  font-weight: 600;
}
.theme-demo {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 52px;
}
.theme-demo span {
  height: 12px;
  background: var(--p-primary-color);
}
.theme-demo span + span {
  width: 70%;
  background: color-mix(in srgb, var(--p-primary-color) 30%, transparent);
}
.theme-demo[data-t='aura'] span { border-radius: 6px; }
.theme-demo[data-t='lara'] span { border-radius: 4px; }
.theme-demo[data-t='material'] span { border-radius: 2px; }
.theme-demo[data-t='nora'] span { border-radius: 0; }

.preview {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
  border-radius: 10px;
  background: var(--app-bg);
  border: 1px dashed var(--p-content-border-color);
}
.pv-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}
.pv-logo {
  width: 40px;
  height: 40px;
  object-fit: contain;
  border-radius: 10px;
}

.logo-row {
  display: flex;
  align-items: center;
  gap: 16px;
}
.logo-img {
  width: 64px;
  height: 64px;
  object-fit: contain;
  border-radius: 14px;
  border: 1px solid var(--p-content-border-color);
  background: var(--p-content-background);
}

.savebar {
  position: fixed;
  left: 50%;
  bottom: 20px;
  transform: translateX(-50%);
  z-index: 30;
  width: min(720px, calc(100vw - 32px));
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 12px 16px;
  box-shadow: 0 10px 30px rgb(15 23 42 / 0.18);
  font-weight: 500;
}
.bar-enter-active,
.bar-leave-active {
  transition: opacity 0.2s, transform 0.2s;
}
.bar-enter-from,
.bar-leave-to {
  opacity: 0;
  transform: translate(-50%, 12px);
}

@media (max-width: 600px) {
  .grid-2 {
    grid-template-columns: 1fr;
  }
  .themes {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
