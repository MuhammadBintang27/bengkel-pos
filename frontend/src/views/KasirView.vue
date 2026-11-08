<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import BayarDialog from '@/components/BayarDialog.vue'
import BIcon from '@/components/BIcon.vue'
import EditBarisDialog from '@/components/kasir/EditBarisDialog.vue'
import KasirGrid from '@/components/kasir/KasirGrid.vue'
import KasirKeranjang from '@/components/kasir/KasirKeranjang.vue'
import SatuanDialog from '@/components/kasir/SatuanDialog.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useMobile } from '@/composables/useMobile'
import { useKasirStore } from '@/stores/kasir'
import { rp } from '@/utils/format'

// Kasir sparepart: grid barang di kiri, keranjang di kanan (laci bawah di HP).
// Keranjang ada di browser dan dikirim sekali saat bayar; server memeriksa ulang stok dan harga.
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const store = useKasirStore()
const mobile = useMobile()
const grid = ref()

const dlg = ref({ satuan: false, edit: false, bayar: false, cart: false })
const pilihan = ref(null)
const barisEdit = ref(null)
let idsDijual = []

const subtitle = computed(() => (store.jumlahItem ? `${store.jumlahItem} jenis barang di keranjang` : 'Ketuk barang untuk mulai menjual'))

onMounted(async () => {
  grid.value?.fokus()
  // Keranjang tersimpan dari sesi sebelumnya: samakan stok dan harga dengan data terbaru.
  if (store.jumlahItem) store.segarkan().catch(() => {})
})

const pesan = (detail) => toast.add({ severity: 'warn', summary: 'Tidak bisa ditambahkan', detail, life: 4500 })

function tambah(sp, satuan, qty = 1) {
  const r = store.tambah(sp, satuan, qty)
  if (!r.ok) return pesan(r.pesan)
  grid.value?.fokus()
}

/** Barang dengan banyak satuan menanyakan satuan dulu; yang satu satuan (atau hasil scan barcode) langsung masuk. */
function pilih(sp, satuanBarcode) {
  if (satuanBarcode) return tambah(sp, satuanBarcode)
  if (sp.satuans.length > 1) {
    pilihan.value = sp
    return (dlg.value.satuan = true)
  }
  return tambah(sp, sp.satuans[0])
}

function edit(b) {
  barisEdit.value = b
  dlg.value.edit = true
}
function simpanEdit(b, nilai) {
  const r = store.ubah(b, nilai)
  if (!r.ok) pesan(r.pesan)
}

function bayar() {
  idsDijual = [...store.ids]
  dlg.value.bayar = true
  dlg.value.cart = false
}
// Bayar berhasil: stok barang yang terjual berubah di server, segarkan grid.
const sudahBayar = () => grid.value?.segarkan(idsDijual)
// Bayar ditolak (stok/harga berubah): ambil data terbaru supaya keranjang dan total sesuai kenyataan.
async function gagalBayar() {
  try {
    const items = await store.segarkan()
    grid.value?.segarkan(items.map((s) => s.id))
  } catch {
    /* pesan dari server sudah tampil di dialog */
  }
}
const kirimBayar = (metode, dibayar) => store.kirim(metode, dibayar)

function kosongkan() {
  confirm.require({
    header: 'Kosongkan keranjang?',
    message: `${store.jumlahItem} jenis barang akan dibuang dari keranjang.`,
    icon: 'pi pi-trash',
    acceptProps: { label: 'Kosongkan', severity: 'danger' },
    rejectProps: { label: 'Batal', severity: 'secondary', outlined: true },
    accept: () => store.kosongkan(),
  })
}

function buang(id) {
  confirm.require({
    header: 'Buang keranjang tertunda?',
    message: 'Isinya akan hilang dan tidak bisa dikembalikan.',
    icon: 'pi pi-trash',
    acceptProps: { label: 'Buang', severity: 'danger' },
    rejectProps: { label: 'Batal', severity: 'secondary', outlined: true },
    accept: () => store.buang(id),
  })
}

function buka(id) {
  store.buka(id)
  dlg.value.cart = false
}
</script>

<template>
  <div class="kasir">
    <PageHeader title="Kasir Sparepart" :subtitle="subtitle">
      <Button label="Papan Servis" icon="pi pi-th-large" severity="secondary" outlined @click="router.push('/servis')" />
    </PageHeader>

    <div class="body">
      <KasirGrid ref="grid" @pilih="pilih" />
      <KasirKeranjang v-if="!mobile" @bayar="bayar" @edit="edit" @kosongkan="kosongkan" @buka="buka" @buang="buang" @pesan="pesan" />
    </div>

    <!-- HP: bar bawah + laci keranjang -->
    <template v-if="mobile">
      <button class="cart-bar" @click="dlg.cart = true">
        <span><BIcon name="jual" :size="20" /> {{ store.jumlahItem }} jenis barang</span>
        <strong class="num">{{ rp(store.total) }}</strong>
      </button>
      <Drawer v-model:visible="dlg.cart" position="bottom" header="Keranjang" class="cart-drawer">
        <KasirKeranjang @bayar="bayar" @edit="edit" @kosongkan="kosongkan" @buka="buka" @buang="buang" @pesan="pesan" />
      </Drawer>
    </template>

    <SatuanDialog v-model:visible="dlg.satuan" :sparepart="pilihan" @tambah="tambah" />
    <EditBarisDialog v-model:visible="dlg.edit" :baris="barisEdit" @simpan="simpanEdit" @hapus="store.hapus" />
    <BayarDialog v-model:visible="dlg.bayar" :total="store.total" :kirim="kirimBayar" @paid="sudahBayar" @gagal="gagalBayar" />
  </div>
</template>

<style scoped>
.kasir {
  /* tinggi layar dikurangi topbar (64) dan padding konten (2 × 24) */
  height: calc(100dvh - 112px);
  min-height: 520px;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.kasir :deep(.ph) {
  margin-bottom: 14px;
}
.body {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 390px;
  grid-template-rows: minmax(0, 1fr);
  gap: 14px;
}
.cart-bar {
  position: fixed;
  left: 12px;
  right: 12px;
  bottom: calc(12px + env(safe-area-inset-bottom));
  z-index: 20;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border: 0;
  border-radius: 14px;
  font: inherit;
  font-size: 1.05rem;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
  box-shadow: 0 6px 20px rgb(0 0 0 / 0.25);
}
.cart-bar span {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
@media (max-width: 900px) {
  .kasir {
    height: calc(100dvh - 96px);
  }
  .body {
    grid-template-columns: 1fr;
    padding-bottom: 72px;
  }
}
</style>

<style>
.cart-drawer {
  height: 88dvh !important;
}
</style>
