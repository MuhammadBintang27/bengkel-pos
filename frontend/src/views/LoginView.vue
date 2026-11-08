<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { errMsg, fieldErrors, get } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import TunasLogo from '@/components/TunasLogo.vue'
import { applyTheme, loadCachedTampilan } from '@/composables/useAppTheme'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const form = reactive({ username: '', password: '' })
const errors = ref({})
const error = ref('')
const loading = ref(false)
const tampilan = ref(loadCachedTampilan())

onMounted(async () => {
  try {
    const res = await get('/tampilan')
    tampilan.value = { ...tampilan.value, ...res.data }
    applyTheme(res.data)
  } catch {
    /* server belum bisa dihubungi: pakai tampilan tersimpan, error muncul saat login */
  }
})

async function submit() {
  errors.value = {}
  error.value = ''
  loading.value = true
  try {
    await auth.login(form.username.trim(), form.password)
    const next = typeof route.query.next === 'string' && route.query.next.startsWith('/') ? route.query.next : auth.home
    router.replace(next)
  } catch (e) {
    errors.value = fieldErrors(e)
    if (!Object.keys(errors.value).length) error.value = errMsg(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login">
    <section class="hero" aria-hidden="true">
      <div class="hero-inner">
        <div class="hero-brand">
          <TunasLogo :size="56" class="hero-logo" />
          <span>Tunas Bengkel</span>
        </div>
        <h2>Kelola bengkel motor dalam satu tempat</h2>
        <ul>
          <li><span class="li-ico"><BIcon name="motor" :size="22" /></span> Servis, penjualan sparepart, dan nota</li>
          <li><span class="li-ico"><BIcon name="stok" :size="22" /></span> Stok dan penerimaan barang dari supplier</li>
          <li><span class="li-ico"><BIcon name="mekanik" :size="22" /></span> Komisi mekanik dan rekap kas harian</li>
        </ul>
      </div>
    </section>

    <section class="form-side">
      <div class="card">
        <div class="brand">
          <img v-if="tampilan.logo" :src="tampilan.logo" alt="" class="logo" />
          <TunasLogo v-else :size="52" />
          <div>
            <h1>{{ tampilan.nama_bengkel }}</h1>
            <p class="muted">Masuk untuk melanjutkan</p>
          </div>
        </div>

        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
          <div class="field">
            <label for="username">Username</label>
            <IconField>
              <InputIcon class="pi pi-user" />
              <InputText
                id="username"
                v-model="form.username"
                autocomplete="username"
                autofocus
                fluid
                :invalid="!!errors.username"
                placeholder="Masukkan username"
              />
            </IconField>
            <small v-if="errors.username" class="err">{{ errors.username }}</small>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <Password
              v-model="form.password"
              input-id="password"
              :feedback="false"
              toggle-mask
              fluid
              :invalid="!!errors.password"
              autocomplete="current-password"
              placeholder="Masukkan password"
            />
            <small v-if="errors.password" class="err">{{ errors.password }}</small>
          </div>

          <Button type="submit" label="Masuk" icon="pi pi-sign-in" :loading="loading" class="mt-2" fluid size="large" />
        </form>

        <p class="foot muted">Lupa password? Hubungi pemilik bengkel untuk mengatur ulang.</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.login {
  min-height: 100dvh;
  display: grid;
  grid-template-columns: 1fr 1fr;
}
.hero {
  background:
    radial-gradient(circle at 20% 20%, color-mix(in srgb, #fff 14%, transparent), transparent 40%),
    linear-gradient(150deg, var(--p-primary-600), var(--p-primary-900));
  color: #fff;
  display: grid;
  place-items: center;
  padding: 48px;
}
.hero-inner {
  max-width: 420px;
}
.hero-brand {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 32px;
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: -0.01em;
}
/* Di atas latar warna utama, logo dibalik: kotak putih, gambar berwarna. */
.hero-logo :deep(.bg) {
  fill: #fff;
}
.hero-logo :deep(.fg) {
  fill: var(--p-primary-700);
}
.hero-logo :deep(.stem) {
  stroke: var(--p-primary-700);
}
.li-ico {
  width: 40px;
  height: 40px;
  flex: none;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: rgb(255 255 255 / 0.15);
}
.hero h2 {
  font-size: 2rem;
  line-height: 1.2;
  margin-bottom: 24px;
}
.hero ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
  font-size: 1.05rem;
  opacity: 0.95;
}
.hero li {
  display: flex;
  gap: 10px;
  align-items: center;
}
.form-side {
  display: grid;
  place-items: center;
  padding: 32px 16px;
}
.card {
  width: 100%;
  max-width: 400px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.brand {
  display: flex;
  align-items: center;
  gap: 14px;
}
.brand h1 {
  font-size: 1.5rem;
}
.brand p {
  margin: 2px 0 0;
}
.logo {
  width: 52px;
  height: 52px;
  flex: none;
  border-radius: 14px;
  object-fit: contain;
}
.foot {
  font-size: 0.88rem;
  text-align: center;
  margin: 0;
}

@media (max-width: 900px) {
  .login {
    grid-template-columns: 1fr;
  }
  .hero {
    display: none;
  }
}
</style>
