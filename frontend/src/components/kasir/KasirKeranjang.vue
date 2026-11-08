<script setup>
import { computed, ref } from 'vue'
import { useKasirStore } from '@/stores/kasir'
import { qty as fmtQty, rp } from '@/utils/format'

// Keranjang lokal: semua perubahan instan (tanpa ke server). Pesan stok kurang dikembalikan lewat event `pesan`.
const emit = defineEmits(['bayar', 'edit', 'kosongkan', 'buka', 'buang', 'pesan'])
const store = useKasirStore()

const menu = ref()
const aksi = computed(() => [
  { label: 'Keranjang baru (tahan yang ini)', icon: 'pi pi-pause', disabled: !store.jumlahItem, command: () => store.tahan() },
  { separator: true },
  { label: 'Kosongkan keranjang', icon: 'pi pi-trash', disabled: !store.jumlahItem, command: () => emit('kosongkan') },
])

function ubah(b, delta) {
  const r = store.ubah(b, { qty: b.qty + delta })
  if (!r.ok) emit('pesan', r.pesan)
}
</script>

<template>
  <section class="panel cart">
    <header class="head">
      <i class="pi pi-shopping-cart ikon" />
      <strong>Keranjang</strong>
      <Tag v-if="store.jumlahItem" :value="`${store.jumlahItem} jenis`" severity="secondary" />
      <span class="kanan">
        <Button icon="pi pi-ellipsis-v" text rounded severity="secondary" aria-label="Aksi keranjang" aria-haspopup="true" @click="menu.toggle($event)" />
        <Menu ref="menu" :model="aksi" popup />
      </span>
    </header>

    <nav v-if="store.tertunda.length" class="tunda" aria-label="Keranjang tertunda">
      <span class="muted">Tertunda:</span>
      <span v-for="(x, n) in store.tertunda" :key="x.id" class="chip">
        <button class="pill" :aria-label="`Buka keranjang tertunda ${n + 1}`" @click="emit('buka', x.id)">{{ n + 1 }} <small>{{ rp(store.totalTunda(x)) }}</small></button>
        <button class="buang" aria-label="Buang keranjang tertunda" @click="emit('buang', x.id)"><i class="pi pi-times" /></button>
      </span>
    </nav>

    <div v-if="!store.jumlahItem" class="empty">
      <i class="pi pi-shopping-cart" />
      <strong>Keranjang masih kosong</strong>
      <small>Ketuk barang di sebelah, atau scan barcode</small>
    </div>

    <ul v-else class="items">
      <li v-for="b in store.baris" :key="b.key" class="item">
        <button class="nm" :aria-label="`Ubah ${b.nama}`" @click="emit('edit', b)">
          <strong>{{ b.nama }}</strong>
          <small class="muted">
            {{ rp(b.harga) }} / {{ b.satuan }}<template v-if="b.diskon"> · potongan {{ rp(b.diskon) }}</template>
          </small>
          <small v-if="b.lokasi" class="rak"><i class="pi pi-map-marker" /> Rak {{ b.lokasi }}</small>
        </button>
        <span class="qty">
          <button class="step" aria-label="Kurangi" @click="ubah(b, -1)"><i class="pi pi-minus" /></button>
          <b class="num">{{ fmtQty(b.qty) }}</b>
          <button class="step" aria-label="Tambah" @click="ubah(b, 1)"><i class="pi pi-plus" /></button>
        </span>
        <strong class="sub num">{{ rp(store.subtotal(b)) }}</strong>
        <button class="hapus" aria-label="Hapus" @click="store.hapus(b)"><i class="pi pi-times" /></button>
      </li>
    </ul>

    <footer class="foot">
      <div class="row total">
        <span>Total</span>
        <strong class="num">{{ rp(store.total) }}</strong>
      </div>
      <Button label="Bayar" icon="pi pi-wallet" size="large" fluid :disabled="!store.jumlahItem" @click="emit('bayar')" />
    </footer>
  </section>
</template>

<style scoped>
.cart {
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}
.head {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--p-content-border-color);
}
.ikon {
  color: var(--accent-text);
}
.kanan {
  margin-left: auto;
}
.tunda {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  overflow-x: auto;
  border-bottom: 1px solid var(--p-content-border-color);
  font-size: 0.88rem;
}
.chip {
  flex: none;
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--p-content-border-color);
  border-radius: 999px;
  background: var(--p-content-background);
}
.chip:hover {
  border-color: var(--p-primary-color);
}
.pill {
  padding: 4px 6px 4px 12px;
  border: 0;
  background: none;
  color: var(--p-text-color);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}
.pill small {
  margin-left: 4px;
  font-weight: 500;
  opacity: 0.8;
}
.buang {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  margin-right: 4px;
  border: 0;
  border-radius: 50%;
  background: none;
  color: var(--p-text-muted-color);
  font-size: 0.65rem;
  cursor: pointer;
}
.buang:hover {
  color: var(--p-red-500);
  background: color-mix(in srgb, var(--p-red-500) 12%, transparent);
}
.empty {
  flex: 1;
  display: grid;
  place-content: center;
  justify-items: center;
  gap: 6px;
  padding: 24px;
  text-align: center;
  color: var(--p-text-muted-color);
}
.empty .pi {
  font-size: 2.4rem;
}
.items {
  flex: 1;
  overflow-y: auto;
  margin: 0;
  padding: 0;
  list-style: none;
}
.item {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto 24px;
  align-items: center;
  gap: 8px;
  padding: 9px 12px;
  border-bottom: 1px solid var(--p-content-border-color);
}
.nm {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  min-width: 0;
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.nm strong {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.nm:hover strong {
  color: var(--accent-text);
}
.rak {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 2px;
  padding: 0 7px;
  border-radius: 6px;
  font-weight: 700;
  background: color-mix(in srgb, var(--p-text-color) 8%, transparent);
}
.rak .pi {
  font-size: 0.7rem;
  color: var(--accent-text);
}
.qty {
  display: flex;
  align-items: center;
  gap: 6px;
}
.qty b {
  min-width: 2ch;
  text-align: center;
}
.step,
.hapus {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: 50%;
  font-size: 0.72rem;
  color: var(--p-text-color);
  background: color-mix(in srgb, var(--p-text-color) 8%, transparent);
  cursor: pointer;
}
.step:hover {
  background: color-mix(in srgb, var(--p-primary-color) 22%, transparent);
}
.hapus {
  width: 24px;
  height: 24px;
  background: none;
  color: var(--p-text-muted-color);
}
.hapus:hover {
  color: var(--p-red-500);
  background: color-mix(in srgb, var(--p-red-500) 12%, transparent);
}
.sub {
  min-width: 5rem;
  text-align: right;
}
.foot {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px;
  border-top: 1px solid var(--p-content-border-color);
  background: color-mix(in srgb, var(--p-primary-color) 4%, var(--p-content-background));
}
.row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
}
.total span {
  font-weight: 600;
}
.total strong {
  font-size: 1.8rem;
  color: var(--accent-text);
}
</style>
