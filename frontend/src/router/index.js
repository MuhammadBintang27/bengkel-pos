import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const OK = ['owner', 'kasir'] // owner selalu lolos; daftar ini untuk peran lain

// meta.roles kosong = semua peran yang sudah login. Pengecekan sebenarnya tetap di API.
const routes = [
  { path: '/login', component: () => import('@/views/LoginView.vue'), meta: { public: true, layout: 'blank' } },

  { path: '/', component: () => import('@/views/DashboardView.vue'), meta: { roles: OK, title: 'Dashboard' } },
  { path: '/akun', component: () => import('@/views/AkunView.vue'), meta: { title: 'Akun Saya' } },

  { path: '/servis', component: () => import('@/views/PapanServisView.vue'), meta: { roles: OK, title: 'Papan Servis' } },
  { path: '/kasir', component: () => import('@/views/KasirView.vue'), meta: { roles: OK, title: 'Kasir Sparepart' } },
  { path: '/transaksi', component: () => import('@/views/TransaksiListView.vue'), meta: { roles: OK, title: 'Daftar Transaksi' } },
  { path: '/transaksi/servis/baru', component: () => import('@/views/ServisBaruView.vue'), meta: { roles: OK, title: 'Motor Masuk' } },
  { path: '/transaksi/penjualan/baru', redirect: '/kasir' },
  { path: '/transaksi/:id(\\d+)', component: () => import('@/views/TransaksiDetailView.vue'), meta: { roles: OK, title: 'Detail Transaksi' } },

  { path: '/pembelian', component: () => import('@/views/PembelianListView.vue'), meta: { roles: OK, title: 'Penerimaan Barang' } },
  { path: '/pembelian/baru', component: () => import('@/views/PembelianView.vue'), meta: { roles: OK, title: 'Penerimaan Baru' } },
  { path: '/pembelian/:id(\\d+)', component: () => import('@/views/PembelianView.vue'), meta: { roles: OK, title: 'Detail Penerimaan' } },
  { path: '/stok', component: () => import('@/views/StokView.vue'), meta: { roles: OK, title: 'Stok Sparepart' } },
  { path: '/stok/:id(\\d+)', component: () => import('@/views/KartuStokView.vue'), meta: { roles: OK, title: 'Kartu Stok' } },
  { path: '/riwayat', component: () => import('@/views/RiwayatView.vue'), meta: { roles: OK, title: 'Riwayat Servis' } },

  { path: '/saldo', component: () => import('@/views/SaldoView.vue'), meta: { roles: ['owner'], title: 'Saldo Mekanik' } },
  { path: '/saldo/:id(\\d+)', component: () => import('@/views/SaldoMekanikView.vue'), meta: { roles: ['owner'], title: 'Saldo Mekanik' } },
  { path: '/pencairan', component: () => import('@/views/PencairanListView.vue'), meta: { roles: ['owner'], title: 'Riwayat Pencairan' } },
  { path: '/pencairan/:id(\\d+)', component: () => import('@/views/PencairanView.vue'), meta: { roles: ['owner'], title: 'Detail Pencairan' } },

  { path: '/laporan', component: () => import('@/views/LaporanView.vue'), meta: { roles: ['owner'], title: 'Laporan' } },
  { path: '/laporan/kas', component: () => import('@/views/KasView.vue'), meta: { roles: OK, title: 'Rekap Kas' } },

  { path: '/master/:jenis', component: () => import('@/views/MasterView.vue'), meta: { roles: OK, title: 'Master Data' } },
  { path: '/sparepart', component: () => import('@/views/SparepartListView.vue'), meta: { roles: ['owner'], title: 'Sparepart' } },
  { path: '/sparepart/baru', component: () => import('@/views/SparepartFormView.vue'), meta: { roles: ['owner'], title: 'Sparepart Baru' } },
  { path: '/sparepart/:id(\\d+)', component: () => import('@/views/SparepartFormView.vue'), meta: { roles: ['owner'], title: 'Ubah Sparepart' } },
  { path: '/pengaturan', component: () => import('@/views/PengaturanView.vue'), meta: { roles: ['owner'], title: 'Pengaturan' } },

  { path: '/mekanik/pekerjaan', component: () => import('@/views/PekerjaanListView.vue'), meta: { roles: ['mekanik'], title: 'Pekerjaan Saya' } },
  { path: '/mekanik/pekerjaan/:id(\\d+)', component: () => import('@/views/PekerjaanView.vue'), meta: { roles: ['mekanik'], title: 'Detail Pekerjaan' } },
  { path: '/mekanik/saldo', component: () => import('@/views/SaldoSayaView.vue'), meta: { roles: ['mekanik'], title: 'Saldo Saya' } },

  { path: '/cetak/nota/:id(\\d+)', component: () => import('@/views/NotaView.vue'), meta: { roles: OK, layout: 'blank', title: 'Nota' } },
  { path: '/cetak/slip/:id(\\d+)', component: () => import('@/views/SlipView.vue'), meta: { roles: ['mekanik'], layout: 'blank', title: 'Slip Komisi' } },

  { path: '/:pathMatch(.*)*', component: () => import('@/views/TidakDitemukanView.vue'), meta: { title: 'Halaman tidak ditemukan' } },
]

// Base '/' walau aset di-build ke /spa/: Laravel melayani index.html di semua path.
const router = createRouter({ history: createWebHistory('/'), routes, scrollBehavior: () => ({ top: 0 }) })

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.checked) await auth.fetchMe()

  if (to.meta.public) return auth.user ? auth.home : true
  if (!auth.user) return { path: '/login', query: to.fullPath !== '/' ? { next: to.fullPath } : {} }
  if (!auth.can(to.meta.roles)) return auth.home
  return true
})

export default router
