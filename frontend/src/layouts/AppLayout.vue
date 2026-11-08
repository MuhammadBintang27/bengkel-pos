<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useMobile } from '@/composables/useMobile'
import { useAuthStore } from '@/stores/auth'
import BIcon from '@/components/BIcon.vue'
import TunasLogo from '@/components/TunasLogo.vue'
import { ROLE_LABEL } from '@/utils/format'

const OK = ['owner', 'kasir']

// Menu samping. roles kosong = semua peran; owner selalu melihat semuanya kecuali menu khusus mekanik.
const NAV = [
  { label: 'Dashboard', icon: 'dashboard', to: '/', roles: OK },
  {
    label: 'Transaksi',
    icon: 'nota',
    roles: OK,
    children: [
      { label: 'Papan Servis', to: '/servis' },
      { label: 'Kasir Sparepart', to: '/kasir' },
      { label: 'Daftar Transaksi', to: '/transaksi' },
    ],
  },
  { label: 'Riwayat Servis', icon: 'riwayat', to: '/riwayat', roles: OK },
  {
    label: 'Persediaan',
    icon: 'stok',
    roles: OK,
    children: [
      { label: 'Penerimaan Barang', to: '/pembelian' },
      { label: 'Stok Sparepart', to: '/stok' },
    ],
  },
  {
    label: 'Mekanik',
    icon: 'mekanik',
    roles: ['owner'],
    children: [
      { label: 'Saldo & Kasbon', to: '/saldo' },
      { label: 'Riwayat Pencairan', to: '/pencairan' },
    ],
  },
  {
    label: 'Laporan',
    icon: 'laporan',
    roles: OK,
    children: [
      { label: 'Penjualan & Laba', to: '/laporan', roles: ['owner'] },
      { label: 'Rekap Kas', to: '/laporan/kas' },
    ],
  },
  {
    label: 'Master Data',
    icon: 'master',
    roles: OK,
    children: [
      { label: 'Pelanggan', to: '/master/pelanggan' },
      { label: 'Kendaraan', to: '/master/kendaraan' },
      { label: 'Sparepart', to: '/sparepart', roles: ['owner'] },
      { label: 'Jasa', to: '/master/jasa', roles: ['owner'] },
      { label: 'Mekanik', to: '/master/mekanik', roles: ['owner'] },
      { label: 'Supplier', to: '/master/supplier', roles: ['owner'] },
      { label: 'Rak', to: '/master/rak', roles: ['owner'] },
      { label: 'Kategori & Satuan', to: '/master/kategori-sparepart', roles: ['owner'] },
      { label: 'Pengguna', to: '/master/pengguna', roles: ['owner'] },
    ],
  },
  { label: 'Pengaturan', icon: 'pengaturan', to: '/pengaturan', roles: ['owner'] },

  { label: 'Pekerjaan Saya', icon: 'servis', to: '/mekanik/pekerjaan', roles: ['mekanik'], only: true },
  { label: 'Saldo Saya', icon: 'kas', to: '/mekanik/saldo', roles: ['mekanik'], only: true },
]

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const confirm = useConfirm()
const mobile = useMobile()

const collapsed = ref(false)
const drawer = ref(false)
const expanded = reactive({})
const userMenu = ref()
const q = ref('')
const online = ref(navigator.onLine)

const visible = (n) => (n.only ? n.roles.includes(auth.role) : auth.can(n.roles))
const menu = computed(() =>
  NAV.filter(visible)
    .map((n) => (n.children ? { ...n, children: n.children.filter(visible) } : n))
    .filter((n) => !n.children || n.children.length),
)
// Kotak cari menu: cocok dengan nama menu, atau dengan salah satu sub-menunya (hanya yang cocok yang tampil).
const nav = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return menu.value
  return menu.value
    .map((n) => {
      if (n.label.toLowerCase().includes(term)) return n
      const children = n.children?.filter((c) => c.label.toLowerCase().includes(term))
      return children?.length ? { ...n, children } : null
    })
    .filter(Boolean)
})

const showSide = computed(() => (mobile.value ? drawer.value : !collapsed.value))
const inGroup = (n) => n.children?.some((c) => route.path === c.to || route.path.startsWith(c.to + '/'))
const isOpen = (n) => expanded[n.label] ?? inGroup(n)

const brand = computed(() => auth.pengaturan.nama_bengkel || 'Tunas Bengkel')

const userItems = computed(() => [
  { label: auth.user?.nama, items: [
    { label: 'Akun saya', icon: 'pi pi-user', command: () => router.push('/akun') },
    { separator: true },
    { label: 'Keluar', icon: 'pi pi-sign-out', command: askLogout },
  ] },
])

function toggleSide() {
  if (mobile.value) drawer.value = !drawer.value
  else collapsed.value = !collapsed.value
}

function askLogout() {
  confirm.require({
    header: 'Keluar dari aplikasi?',
    message: 'Anda perlu memasukkan username dan password lagi untuk masuk.',
    icon: 'pi pi-sign-out',
    acceptProps: { label: 'Keluar', severity: 'danger' },
    rejectProps: { label: 'Batal', severity: 'secondary', outlined: true },
    accept: async () => {
      await auth.logout()
      router.push('/login')
    },
  })
}

const setOnline = () => (online.value = navigator.onLine)
watch(() => route.path, () => (drawer.value = false))
onMounted(() => {
  window.addEventListener('online', setOnline)
  window.addEventListener('offline', setOnline)
})
onBeforeUnmount(() => {
  window.removeEventListener('online', setOnline)
  window.removeEventListener('offline', setOnline)
})
</script>

<template>
  <div class="shell">
    <div v-if="mobile && drawer" class="scrim" @click="drawer = false" />

    <aside class="side" :class="{ open: showSide, floating: mobile }" aria-label="Menu utama">
      <RouterLink :to="auth.home" class="brand">
        <img v-if="auth.pengaturan.logo" :src="auth.pengaturan.logo" alt="" class="mark img" />
        <TunasLogo v-else :size="36" />
        <span class="brand-text">
          <strong>{{ brand }}</strong>
          <small>{{ brand === 'Tunas Bengkel' ? 'Sistem Manajemen Bengkel' : 'Tunas Bengkel' }}</small>
        </span>
      </RouterLink>

      <IconField class="search">
        <InputIcon class="pi pi-search" />
        <InputText v-model="q" placeholder="Cari menu…" fluid aria-label="Cari menu" />
      </IconField>

      <div class="caption">MENU</div>
      <nav class="nav">
        <template v-for="n in nav" :key="n.label">
          <RouterLink v-if="n.to" :to="n.to" class="item" exact-active-class="active">
            <BIcon :name="n.icon" class="ni" /> <span>{{ n.label }}</span>
          </RouterLink>
          <template v-else>
            <button
              type="button"
              class="item"
              :class="{ parent: inGroup(n) }"
              :aria-expanded="isOpen(n) || !!q"
              @click="expanded[n.label] = !isOpen(n)"
            >
              <BIcon :name="n.icon" class="ni" /> <span>{{ n.label }}</span>
              <i class="pi chev" :class="isOpen(n) || q ? 'pi-chevron-down' : 'pi-chevron-right'" />
            </button>
            <div v-show="isOpen(n) || q" class="children">
              <RouterLink v-for="c in n.children" :key="c.to" :to="c.to" class="sub" exact-active-class="active">
                <i class="dot" /> {{ c.label }}
              </RouterLink>
            </div>
          </template>
        </template>
        <p v-if="!nav.length" class="tak-ada muted">Menu tidak ditemukan</p>
      </nav>

      <div class="status"><i class="dot" :class="online ? 'on' : 'off'" /> {{ online ? 'Online' : 'Offline' }}</div>
    </aside>

    <div class="main">
      <header class="top">
        <Button icon="pi pi-bars" variant="outlined" severity="secondary" aria-label="Buka/tutup menu" @click="toggleSide" />
        <span class="page-title">{{ route.meta.title }}</span>

        <button type="button" class="user" aria-haspopup="true" @click="userMenu.toggle($event)">
          <Avatar :label="(auth.user?.nama || '?')[0].toUpperCase()" shape="circle" class="avatar" />
          <span class="who">
            <strong>{{ auth.user?.nama }}</strong>
            <small>{{ ROLE_LABEL[auth.role] }}<template v-if="auth.user?.mekanik"> · {{ auth.user.mekanik }}</template></small>
          </span>
          <i class="pi pi-angle-down muted" />
        </button>
        <Menu ref="userMenu" :model="userItems" popup />
      </header>

      <main class="content">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.shell {
  display: flex;
  height: 100dvh;
  overflow: hidden;
}

.side {
  width: 0;
  flex: none;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  /* sedikit nuansa warna utama, seperti sidebar POS Warkop */
  background: color-mix(in srgb, var(--p-primary-color) 7%, var(--p-content-background));
  border-right: 1px solid var(--p-content-border-color);
  transition: width 0.2s ease;
}
.side.open {
  width: 260px;
}
.side.floating {
  position: fixed;
  inset: 0 auto 0 0;
  z-index: 50;
  transition: none;
  box-shadow: 8px 0 24px rgb(0 0 0 / 0.18);
}
.scrim {
  position: fixed;
  inset: 0;
  background: rgb(15 23 42 / 0.45);
  z-index: 40;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  height: 64px;
  padding: 0 18px;
  flex: none;
  white-space: nowrap;
  border-bottom: 1px solid var(--p-content-border-color);
}
.brand-text {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
  min-width: 0;
}
.brand-text strong {
  overflow: hidden;
  text-overflow: ellipsis;
}
.brand-text small {
  color: var(--p-text-muted-color);
  font-size: 0.78rem;
}
.mark {
  width: 36px;
  height: 36px;
  flex: none;
  border-radius: 10px;
  display: grid;
  place-items: center;
  font-weight: 700;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}
.mark.img {
  object-fit: contain;
  background: transparent;
}

.search {
  margin: 16px 16px 4px;
  flex: none;
}
.caption {
  padding: 16px 24px 6px;
  flex: none;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--accent-text);
}

.nav {
  flex: 1;
  overflow-y: auto;
  padding: 0 12px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 11px 12px;
  border: 0;
  border-radius: 10px;
  background: none;
  font: inherit;
  font-weight: 600;
  text-align: left;
  color: var(--p-text-color);
  cursor: pointer;
  white-space: nowrap;
}
.item:hover,
.sub:hover {
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
}
.item:focus-visible,
.sub:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: -2px;
}
.item.active,
.sub.active {
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}
.item.parent {
  color: var(--accent-text);
}
.chev {
  margin-left: auto;
  font-size: 0.8rem;
}
.children {
  margin: 2px 0 6px 22px;
  padding-left: 8px;
  border-left: 2px solid color-mix(in srgb, var(--p-primary-color) 25%, transparent);
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.sub {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 9px 12px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.95rem;
  white-space: nowrap;
}
.dot {
  width: 6px;
  height: 6px;
  flex: none;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.7;
}
.tak-ada {
  margin: 12px 8px;
  text-align: center;
}
.status {
  flex: none;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 20px;
  border-top: 1px solid var(--p-content-border-color);
  font-size: 0.9rem;
  font-weight: 600;
}
.status .dot {
  width: 9px;
  height: 9px;
  opacity: 1;
}
.status .on {
  background: var(--p-green-500, #22c55e);
}
.status .off {
  background: var(--p-red-500, #ef4444);
}

.main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.top {
  height: 64px;
  flex: none;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 16px;
  background: color-mix(in srgb, var(--p-primary-color) 9%, var(--p-content-background));
  border-bottom: 1px solid var(--p-content-border-color);
}
.page-title {
  font-weight: 600;
  font-size: 1.05rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.user {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 10px;
  border: 1px solid var(--p-content-border-color);
  border-radius: 12px;
  background: var(--p-content-background);
  font: inherit;
  color: inherit;
  cursor: pointer;
}
.avatar {
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}
.who {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  line-height: 1.25;
}
.who small {
  color: var(--p-text-muted-color);
}
.content {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

@media (max-width: 900px) {
  .content {
    padding: 16px;
  }
}
@media (max-width: 600px) {
  .who,
  .user .pi-angle-down {
    display: none;
  }
}
</style>
