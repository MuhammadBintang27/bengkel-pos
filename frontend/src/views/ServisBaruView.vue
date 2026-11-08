<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { errMsg, fieldErrors, post } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import PageHeader from '@/components/PageHeader.vue'
import PlatInput from '@/components/PlatInput.vue'

// Form servis lengkap. Cukup plat; data lain opsional (alur cepat ada di Papan Servis).
const router = useRouter()
const toast = useToast()

const plat = ref('')
const dikenal = ref(null) // motor yang dipilih dari saran
const form = ref({ keluhan: '', km: null, merk: 'Honda', tipe: '', pemilik_nama: '', pemilik_telepon: '' })
const errors = ref({})
const saving = ref(false)

const baru = computed(() => !dikenal.value && plat.value.replace(/[^A-Za-z0-9]/g, '').length >= 3)
const MERK = ['Honda', 'Yamaha', 'Suzuki', 'Kawasaki', 'TVS', 'Vespa']

async function buat() {
  saving.value = true
  errors.value = {}
  const f = form.value
  const body = { jenis: 'servis', plat: plat.value, keluhan: f.keluhan || null, km: f.km }
  if (baru.value) Object.assign(body, { merk: f.merk || null, tipe: f.tipe || null, pemilik_nama: f.pemilik_nama || null, pemilik_telepon: f.pemilik_telepon || null })
  try {
    const res = await post('/transaksi', body)
    toast.add({ severity: 'success', summary: 'Work order dibuat', detail: res.message, life: 3000 })
    router.replace(`/transaksi/${res.data.id}`)
  } catch (e) {
    errors.value = fieldErrors(e)
    if (!Object.keys(errors.value).length) toast.add({ severity: 'error', summary: 'Gagal membuat', detail: errMsg(e), life: 5000 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <PageHeader title="Motor Masuk (Form Lengkap)" subtitle="Cukup isi plat. Pemilik dan tipe motor boleh dilengkapi nanti." back="/servis" />

  <form class="wrap" @submit.prevent="buat">
    <section class="panel card">
      <header class="card-head">
        <span class="card-ico"><BIcon name="motor" /></span>
        <div><h2>Motor</h2><p class="muted">Ketik plat. Motor yang sudah pernah servis muncul sebagai saran.</p></div>
      </header>

      <div class="field">
        <label for="plat">Plat nomor <span class="req">*</span></label>
        <PlatInput v-model:plat="plat" input-id="plat" size="large" autofocus :invalid="!!errors.plat" @pilih="dikenal = $event" />
        <small v-if="errors.plat" class="err">{{ errors.plat }}</small>
      </div>

      <div v-if="dikenal" class="motor">
        <strong class="plat">{{ dikenal.plat }}</strong>
        <div class="detail">
          <strong>{{ [dikenal.merk, dikenal.tipe].filter(Boolean).join(' ') || 'Data motor belum lengkap' }}</strong>
          <span class="muted">{{ dikenal.pemilik || 'Pemilik belum diisi' }}<template v-if="dikenal.telepon"> · {{ dikenal.telepon }}</template></span>
        </div>
      </div>

      <div v-if="baru" class="baru">
        <p class="muted"><i class="pi pi-info-circle" /> Plat baru, akan didaftarkan otomatis. Data di bawah <strong>boleh dikosongkan</strong>.</p>
        <div class="grid">
          <div class="field">
            <label for="merk">Merk</label>
            <InputText id="merk" v-model="form.merk" fluid list="merk-list" autocomplete="off" />
            <datalist id="merk-list"><option v-for="m in MERK" :key="m" :value="m" /></datalist>
          </div>
          <div class="field">
            <label for="tipe">Tipe</label>
            <InputText id="tipe" v-model="form.tipe" fluid placeholder="Vario 125, Beat, NMAX" autocomplete="off" />
          </div>
          <div class="field">
            <label for="pn">Nama pemilik</label>
            <InputText id="pn" v-model="form.pemilik_nama" fluid autocomplete="off" />
          </div>
          <div class="field">
            <label for="pt">Telepon / WhatsApp</label>
            <InputText id="pt" v-model="form.pemilik_telepon" fluid inputmode="tel" autocomplete="off" />
          </div>
        </div>
      </div>
    </section>

    <section class="panel card">
      <header class="card-head">
        <span class="card-ico"><BIcon name="servis" /></span>
        <div><h2>Keluhan</h2><p class="muted">Apa yang dirasakan pelanggan? Mekanik membacanya dari sini.</p></div>
      </header>
      <div class="grid">
        <div class="field span2">
          <label for="keluhan">Keluhan</label>
          <Textarea id="keluhan" v-model="form.keluhan" rows="3" auto-resize fluid maxlength="500" placeholder="Contoh: mesin brebet, rem bunyi, ganti oli" />
        </div>
        <div class="field">
          <label for="km">Kilometer saat ini</label>
          <InputNumber v-model="form.km" input-id="km" suffix=" km" :min="0" :max-fraction-digits="0" fluid placeholder="12.500" locale="id-ID" />
        </div>
      </div>
    </section>

    <div class="bar">
      <Button type="button" label="Batal" severity="secondary" outlined :disabled="saving" @click="router.push('/servis')" />
      <Button type="submit" label="Buat Work Order" icon="pi pi-arrow-right" icon-pos="right" :loading="saving" :disabled="plat.replace(/[^A-Za-z0-9]/g, '').length < 3" />
    </div>
  </form>
</template>

<style scoped>
.wrap {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.card {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
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
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}
.span2 {
  grid-column: span 2;
}
.motor {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 16px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--p-primary-color) 6%, transparent);
  border: 1px solid color-mix(in srgb, var(--p-primary-color) 25%, transparent);
}
.plat {
  padding: 6px 12px;
  border: 2px solid var(--p-text-color);
  border-radius: 6px;
  letter-spacing: 0.08em;
  font-size: 1.1rem;
  background: var(--p-content-background);
}
.detail {
  display: flex;
  flex-direction: column;
}
.baru {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 10px;
  border: 1px dashed var(--p-content-border-color);
}
.baru p {
  margin: 0;
}
.bar {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
@media (max-width: 640px) {
  .span2 {
    grid-column: auto;
  }
}
</style>
