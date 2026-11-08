# Rancangan Tunas Bengkel — Sistem Manajemen Bengkel Motor

Sistem kasir, servis, stok, pembelian, dan komisi mekanik untuk satu bengkel motor. Satu codebase yang bisa dipasang **lokal** (PC kasir) maupun di **shared hosting**.

## 1. Tujuan
- Mencatat motor masuk, jasa, sparepart, mekanik, dan pembayaran dalam satu alur.
- Melayani **penjualan sparepart langsung** (tanpa servis) di alur yang sama.
- Sparepart punya **banyak satuan** (pcs, box, dus, liter, ...) untuk jual maupun beli.
- **Penerimaan barang dari supplier** menambah stok dan memperbarui harga pokok.
- Stok tidak bisa minus. Nota bisa dicetak.
- Komisi jasa masuk ke **saldo mekanik**, dicairkan **per periode** (harian, mingguan, atau bulanan) dengan slip yang merinci pekerjaan di periode itu. Mekanik bisa kasbon.
- Mekanik login untuk melihat pekerjaan, saldo, dan slip komisinya sendiri.
- Owner melihat omzet, laba kotor, rekap kas harian, pembelian, dan saldo mekanik tanpa hitung manual.

## 2. Stack
| Lapisan | Pilihan |
|---|---|
| Backend | Laravel 12 (PHP 8.2+), **REST API JSON** di `/api` |
| Auth | Laravel Sanctum, **SPA cookie** (session + CSRF), bukan token di `localStorage` |
| Frontend | **SPA** Vue 3 + Vue Router 4 + Pinia, folder terpisah `frontend/` |
| UI | **PrimeVue 4** (tema bisa dipilih, styled mode) + PrimeIcons |
| Layout / utilitas CSS | Tailwind v4 |
| HTTP client | Axios (`withCredentials`, `withXSRFToken`) |
| Build | Vite; `npm run build` di lokal menghasilkan `backend/public/spa` |
| Tes | PHPUnit (Feature, backend), Vitest (logika frontend) |
| Database | MySQL / MariaDB (SQLite untuk tes) |
| Zona waktu / bahasa | Asia/Jakarta, `id` |

- SPA dilayani oleh Laravel dari domain yang sama dengan API, jadi tidak perlu CORS dan cookie Sanctum langsung jalan (termasuk dari HP mekanik).
- Laravel punya satu rute *catch-all* yang mengembalikan `public/spa/index.html` untuk semua path selain `/api` dan `/sanctum`, supaya refresh di `/transaksi/12` tidak 404.
- Tanpa queue worker dan tanpa Node di server, jadi aman untuk shared hosting. Satu-satunya cron adalah `schedule:run`.

## 3. Peran dan akses
| Peran | Boleh |
|---|---|
| **Owner** | Semuanya: master data, transaksi, void, pembelian, stok, saldo dan pencairan, laporan, pengaturan |
| **Kasir** | Pelanggan, kendaraan, transaksi (buat, bayar), penerimaan barang, riwayat servis, rekap kas hari ini |
| **Mekanik** | Pekerjaan yang ditugaskan ke dirinya, ubah status pengerjaan, saldo dan riwayat komisi sendiri |

- Dipasang lewat middleware `role:...`; owner selalu lolos.
- Akun mekanik terhubung ke data mekanik lewat `users.mekanik_id`. Satu mekanik maksimal satu akun.
- Mekanik **tidak melihat** harga beli, laba, saldo mekanik lain, atau transaksi yang tidak melibatkannya.
- Master data, harga, void transaksi lunas, void pembelian, penyesuaian stok, kasbon, dan pencairan hanya untuk owner.

## 4. Master data
| Master | Isi | Contoh |
|---|---|---|
| **Rak** | `nama` (unik), `keterangan` | A1 "Rak oli", B2 "Rak busi". Tampil di kasir supaya kasir tahu di mana mengambil barang |
| **Kategori sparepart** | `nama` | Oli, Busi, Kampas rem, Ban, Aki, Filter, Bearing, Kabel, Lampu, Rantai/gear, Aksesoris |
| **Satuan** | `nama` | pcs, set, pasang, box, dus, pak, botol, liter, meter, roll |
| **Sparepart** | kode, part number, nama, kategori, merk, kompatibel, lokasi rak, satuan dasar, stok min | `BSI-C7HSA` · NGK C7HSA · Busi · NGK · "Beat, Vario, Scoopy" · Rak B2 · pcs |
| **Satuan sparepart** | satuan, isi (dalam satuan dasar), harga jual, barcode | pcs isi 1 Rp20.000 · box isi 10 Rp185.000 |
| **Supplier** | nama, kontak, telepon, alamat, catatan | Toko Sumber Motor, Distributor AHM |
| **Kategori jasa** | `nama` | Servis rutin, Servis besar, Kelistrikan, Mesin, Kaki-kaki, Ban, Body |
| **Jasa** | nama, kategori, harga standar | Ganti oli Rp10.000, Servis CVT Rp50.000, Turun mesin Rp400.000 |
| **Mekanik** | nama, telepon, komisi persen, aktif, akun login | Budi 40% |
| **Pelanggan** | nama, telepon, alamat | |
| **Kendaraan** | pelanggan, plat, merk, tipe, tahun, warna, no rangka | B 1234 XYZ · Honda · Vario 125 · 2021 · Hitam |
| **Pengguna** | nama, username, role, mekanik (bila role mekanik), aktif | |
| **Pengaturan bengkel** | nama bengkel, alamat, telepon, logo, catatan kaki nota, ukuran nota, tampilan (§4.1) | |

### 4.1 Tampilan (tema dan warna)
Owner mengatur tampilan aplikasi dari halaman Pengaturan. Berlaku untuk semua pengguna.

| Pilihan | Nilai | Default |
|---|---|---|
| Tema | Aura, Lara, Material, Nora (preset PrimeVue) | Aura |
| Warna utama | 18 warna bawaan (amber, orange, red, rose, pink, fuchsia, purple, violet, indigo, blue, sky, cyan, teal, emerald, green, lime, yellow, slate) **atau** warna brand sendiri (color picker, hex) | blue |
| Mode | Terang, Gelap, Otomatis (ikut sistem) | Terang |

- Perubahan langsung terlihat sebagai pratinjau sebelum disimpan. Kalau batal, tampilan kembali ke yang tersimpan.
- Warna brand hex dibuatkan palet 50–950 otomatis. Warna teks di atas tombol (putih atau gelap) dipilih otomatis agar kontras tetap terbaca.
- Tersimpan di `pengaturans` dan disalin ke `localStorage` supaya tidak ada kedipan warna default saat halaman dibuka.
- Nota cetak tidak ikut tema (selalu hitam putih).

Merk dan tipe kendaraan berupa teks dengan saran dari data yang sudah ada (AutoComplete), tanpa tabel master terpisah.

## 5. Satuan bertingkat
- Setiap sparepart punya **satu satuan dasar** (satuan terkecil, isi = 1). Stok selalu disimpan dalam satuan dasar.
- Satuan lain didefinisikan dengan **isi** terhadap satuan dasar, contoh:
  - Busi: `pcs` (1), `box` (10)
  - Oli: `liter` (1), `botol 0,8 L` (0,8), `dus` (19,2 = 24 botol × 0,8 L)
  - Baut: `pcs` (1), `pak` (50)
- Setiap satuan punya **harga jual sendiri** (harga per box biasanya lebih murah dari 10 × harga pcs) dan boleh punya barcode sendiri.
- Satuan juga dipakai saat **membeli** dari supplier (beli 2 dus, stok naik 48 botol).
- Di baris transaksi dan pembelian disimpan: `satuan`, `isi`, `qty` (dalam satuan itu), dan `qty_dasar = qty × isi`. Stok dan HPP selalu dihitung dari `qty_dasar`.
- Satuan lain (box, dus) boleh diubah atau dihapus kapan saja: nama, isi, dan harga disalin ke baris transaksi saat dibuat, jadi riwayat tidak berubah.
- **Satuan dasar** selalu ada dengan isi 1 dan terkunci setelah sparepart punya riwayat stok (kalau diubah, angka stok jadi tidak berarti).
- Kode sparepart boleh dikosongkan; dibuatkan otomatis (`SP0001`, dst.). Stok awal (opsional) diisi saat membuat sparepart, disertai harga beli sebagai HPP awal.

## 6. Alur utama

### 6.1 Servis
1. Motor datang. Di **Papan Servis** kasir cukup **mengetik plat** (lalu Enter), keluhan boleh dikosongkan. Plat yang sudah dikenal dipakai ulang; plat baru **didaftarkan otomatis** tanpa pemilik, merk, atau tipe. Itu semua boleh dilengkapi nanti lewat tombol "Lengkapi data motor" di detail transaksi. Hasilnya **transaksi jenis servis** berstatus **Antri**. (Form lengkap dengan merk, tipe, dan pemilik tetap tersedia.)
2. Tambah **baris jasa**: pilih jasa (harga standar jadi acuan, boleh diubah) dan **mekanik per baris**. Persen komisi mekanik di-snapshot ke baris itu.
3. Tambah **baris sparepart**: pilih sparepart dan satuan, stok langsung berkurang. Hapus baris berarti stok kembali.
4. Mekanik (di akunnya) atau kasir memajukan status: Antri → Dikerjakan → Selesai.
5. **Bayar** (dari status terbuka mana pun): tunai / transfer / QRIS. Status jadi **Lunas**, transaksi terkunci, dan komisi masuk saldo mekanik.
6. Cetak nota.

### 6.2 Penjualan langsung
Layar **Kasir Sparepart** bergaya POS: grid barang (chip kategori, pencarian, scan barcode + Enter) di kiri, keranjang di kanan (laci bawah di HP). Keranjang **ada di browser** (tersimpan di `localStorage`, aman dari refresh) dan baru dikirim ke server **sekali saat bayar** lewat `POST /api/penjualan`. Server memeriksa ulang stok dan harga dalam satu transaksi database: kalau ada yang kurang atau berubah, **tidak ada yang tersimpan** dan pesannya menyebut barang mana. Stok baru dikurangi saat bayar, jadi dua kasir bisa memasukkan barang yang sama ke keranjang, dan yang bayar belakangan ditolak bila stok habis. Barang dengan banyak satuan menanyakan satuan dan jumlah dulu. Keranjang bisa **ditahan** (tertunda) lalu dibuka lagi, hanya di komputer yang sama. Tiap kartu dan baris keranjang menampilkan **rak** barang.

### 6.2a Papan Servis
Tampilan seperti denah meja: motor yang sedang di bengkel dikelompokkan per tahap (**Antri, Dikerjakan, Siap Bayar**, plus **Lunas Hari Ini**). Tiap kartu menampilkan plat, motor, pemilik, keluhan, mekanik, lama di bengkel (kuning bila lewat 3 jam, merah lewat 6 jam), dan total. Tombol di kartu memajukan tahap atau langsung ke pembayaran. Papan menyegarkan diri tiap 20 detik. Plat yang sama dicatat dua kali memunculkan peringatan.

### 6.3 Penerimaan barang dari supplier
1. Barang datang. Kasir atau owner buat **penerimaan**: pilih supplier, isi nomor faktur supplier dan tanggal faktur.
2. Isi baris: sparepart (cari nama/kode atau scan barcode), satuan beli (default satuan terbesar, mis. box/dus), qty, harga beli per satuan, diskon baris. Barang dan satuan yang sama digabung. Sparepart yang belum ada ditambahkan dulu di Master Sparepart (hanya owner); dari halaman Stok ada tombol "Terima barang ini" yang langsung mengisi barisnya.
3. Simpan sebagai **draft** (belum memengaruhi stok) atau **terima**. Terima dari form langsung menyimpan dan menerima dalam satu transaksi database: bila ada baris bermasalah, tidak ada yang tersimpan.
4. Saat diterima: stok bertambah sebanyak `qty_dasar` dan **HPP rata-rata tertimbang** diperbarui: `(stok lama × HPP lama + biaya baris setelah diskon) ÷ (stok lama + qty_dasar)`. Stok lama yang nol atau minus tidak ikut dihitung, jadi HPP mengikuti harga beli terbaru. Baris barang yang sama diproses berurutan. Penerimaan lalu terkunci.
5. Pembayaran ke supplier dicatat: **lunas** (tunai/transfer, uang dari laci atau luar laci) atau **tempo** dengan tanggal jatuh tempo. Tempo dilunasi belakangan dari halaman penerimaan. Tempo yang sudah lewat jatuh tempo ditandai **Terlambat**; dashboard menghitung tempo yang jatuh dalam 7 hari.
6. Batal penerimaan (owner): hanya bila stok semua barangnya masih cukup untuk dikurangi kembali (belum terjual atau terpakai); kalau tidak, ditolak dengan pesan yang menyebut barangnya. Alasan wajib. HPP tidak dihitung mundur (rata-rata tidak bisa dibalik). Draft tidak dibatalkan, cukup dihapus.

### 6.3a Stok
Halaman **Stok** menampilkan ringkasan (jenis barang, menipis, habis, nilai persediaan untuk owner), filter status/kategori/rak, dan tombol ke kartu stok, terima barang, dan penyesuaian. **Kartu stok** menampilkan semua pergerakan satu barang (awal, masuk, terjual, dikembalikan, batal penerimaan, penyesuaian) dengan saldo sesudah tiap baris dan tautan ke dokumen asalnya. **Penyesuaian stok** (owner) memasukkan hasil hitung fisik: stok diset ke angka itu, selisihnya tercatat di kartu stok, dan alasan wajib. HPP dan nilai persediaan disembunyikan dari kasir di API.

### 6.4 Halaman mekanik
1. Mekanik login, melihat daftar **pekerjaan saya** (transaksi terbuka yang punya baris jasa dengan namanya).
2. Membuka detail: keluhan, KM, daftar jasa dan sparepart (tanpa harga beli).
3. Memajukan status Antri → Dikerjakan → Selesai.
4. Melihat **saldo saya** dan riwayat komisi/pencairan.

### 6.5 Void transaksi
- Transaksi terbuka: kasir atau owner. Alasan wajib, stok kembali.
- Transaksi lunas: hanya owner. Alasan wajib, stok kembali, komisi dikoreksi lewat mutasi saldo negatif.
- Data tidak pernah dihapus. Disimpan `batal_at`, `batal_user_id`, `batal_alasan`.

### 6.6 Komisi, kasbon, dan pencairan
Setiap mekanik punya **buku komisi**. Isinya baris-baris yang belum dicairkan:

| Jenis | Kapan muncul | Nilai |
|---|---|---|
| Komisi | Transaksi lunas | + (satu baris per mekanik per transaksi) |
| Koreksi | Transaksi lunas di-void | − sebesar komisi transaksi itu |
| Kasbon | Owner memberi uang duluan ke mekanik | − |

**Saldo = jumlah semua baris yang belum dicairkan.**

**Mencairkan (per periode):**
1. Owner buka mekanik di halaman Saldo, pilih **"sampai tanggal"** (default hari ini). "Dari tanggal" terisi otomatis, yaitu tanggal baris tertua yang belum dicairkan.
2. Sistem menampilkan rincian semua baris yang belum dicairkan sampai tanggal itu: tanggal, nomor transaksi, plat, jasa, komisi, lalu kasbon dan koreksi.
3. Total dibayar = komisi − kasbon − koreksi. Owner pilih metode (tunai/transfer) dan **sumber uang** (laci kasir / luar laci).
4. Simpan. Semua baris tadi ditandai milik pencairan ini (`pencairan_id`), lalu **slip komisi** dicetak.
5. Bila totalnya nol atau minus (kasbon lebih besar dari komisi), pencairan tidak bisa dibuat; sisa kasbon ikut terpotong di periode berikutnya.

Jadi pola harian, mingguan, atau bulanan cukup dengan memilih "sampai tanggal". Setiap pencairan punya periode **dari–sampai** yang jelas, dan setiap rupiah di slip bisa dilacak ke transaksinya.

**Kasbon:**
- Owner mencatat kasbon: mekanik, jumlah, metode, sumber uang, keterangan.
- Total kasbon yang belum terpotong tidak boleh melebihi `batas_kasbon` mekanik itu (0 = tidak boleh kasbon).
- Kasbon otomatis terpotong di pencairan berikutnya.

**Void pencairan** (owner, alasan wajib): baris-baris kembali berstatus belum dicairkan.

## 7. Aturan bisnis

### Uang dan hitungan
- Semua uang integer rupiah. Qty desimal (dua angka di belakang koma).
- **Diskon per baris**, nilainya 0 sampai subtotal baris.
- Subtotal baris jasa = `harga − diskon`.
- Subtotal baris sparepart = `round(qty × harga) − diskon` (harga = harga satuan yang dipilih).
- **Total** = Σ subtotal jasa + Σ subtotal sparepart.
- **Komisi per baris jasa** = `floor((harga − diskon) × komisi_persen / 100)`. Sparepart 100% milik bengkel.
- **HPP baris sparepart** = `round(qty_dasar × hpp_satuan_dasar)`, disalin saat baris dibuat.
- **Laba kotor** = total − Σ HPP − Σ komisi.

### Harga pokok (HPP)
- Disimpan per satuan dasar di `spareparts.hpp`.
- Saat penerimaan diterima, dipakai rata-rata tertimbang:
  `hpp_baru = (stok × hpp_lama + qty_dasar × harga_beli_dasar) / (stok + qty_dasar)`,
  dengan `harga_beli_dasar = (qty × harga − diskon) / qty_dasar`.
- Harga beli terakhir per supplier tetap terlihat di riwayat penerimaan.

### Status transaksi
- Urutan: `antri → dikerjakan → selesai → lunas`, plus `batal`. Status hanya maju.
- Bayar boleh dari `antri`, `dikerjakan`, atau `selesai`.
- Transaksi `lunas` atau `batal` **terkunci**.
- **Penjualan langsung** dibuat langsung berstatus `selesai` (siap dibayar), tanpa tahap Antri/Dikerjakan, dan tidak boleh punya baris jasa. Di layar statusnya tampil "Belum dibayar".
- Harga sparepart di transaksi **mengikuti harga jual satuan yang dipilih** dan tidak diketik ulang; potongan harga lewat kolom diskon, supaya tiap potongan tercatat.
- Jumlah sparepart boleh diubah selama transaksi terbuka; stok menyesuaikan selisihnya. Satuan tidak bisa diganti (hapus baris lalu tambah lagi).
- **HPP dan komisi disembunyikan dari kasir** di API, bukan hanya di layar. Hanya owner yang melihat laba kotor per transaksi.
- Batal: transaksi terbuka boleh oleh kasir atau owner; transaksi lunas hanya owner. Baris tetap disimpan untuk riwayat dan stok dikembalikan sekali saja.
- Nota dibuka di tab baru (`/cetak/nota/{id}?print=1` langsung memunculkan dialog cetak). Ukuran kertas 58/80 mm atau A5 bisa diganti saat itu juga tanpa mengubah Pengaturan.

### Pembayaran pelanggan
- Satu transaksi dibayar sekali dan lunas. Tidak ada bon dan tidak ada bayar sebagian (lihat Fase 2).
- Tunai: `dibayar ≥ total`, `kembalian = dibayar − total` disimpan.
- Transfer / QRIS: `dibayar = total`.

### Stok
- Stok **tidak boleh minus**. Tambah baris ditolak bila stok kurang.
- Setiap perubahan stok mencatat `stok_mutasis` (dalam satuan dasar) dan memperbarui `spareparts.stok` di dalam satu DB transaction dengan `lockForUpdate()`.
- Penyesuaian stok manual (owner) wajib keterangan.

### Komisi dan saldo
- Persen komisi diambil dari mekanik saat baris jasa dibuat. Mengubah persen mekanik tidak memengaruhi baris lama.
- Komisi masuk buku **saat lunas**, tanggalnya = `lunas_at`.
- **Saldo mekanik = Σ `saldo_mutasis.jumlah` yang `pencairan_id` masih kosong**, tanpa kolom saldo terpisah.
- Pencairan selalu mengambil **semua** baris belum cair sampai tanggal yang dipilih (tidak ada cair sebagian), dan hanya bila totalnya > 0.
- Void transaksi yang komisinya sudah dicairkan membuat baris koreksi baru (belum cair), yang terpotong di pencairan berikutnya.
- Kasbon belum terpotong ≤ `batas_kasbon`.
- Pencairan dan kasbon dengan sumber **laci kasir** tercatat sebagai uang keluar di rekap kas.

### Data
- Nomor transaksi `INV-YYYYMMDD-NNN`, nomor penerimaan `PB-YYYYMMDD-NNN`. Urut per hari, unique index, dibuat di dalam transaction dengan lock.
- Plat dinormalisasi (huruf besar, tanpa spasi) dan unique.
- Nama, satuan, isi, harga, dan HPP disalin ke baris transaksi, jadi perubahan master tidak mengubah riwayat.
- Master data memakai soft delete.

## 8. Skema database
`?` = nullable.

### Master
| Tabel | Kolom utama |
|---|---|
| `users` | `nama`, `username` (unique), `password`, `role` (owner/kasir/mekanik), `mekanik_id`? (unique), `aktif` |
| `pengaturans` | `kunci` (unique), `nilai` |
| `satuans` | `nama` (unique) |
| `raks` | `nama` (unique), `keterangan`? |
| `kategori_spareparts` | `nama` |
| `spareparts` | `kode` (unique), `part_number`?, `nama`, `kategori_id`, `merk`?, `kompatibel`?, `rak_id`?, `satuan_dasar_id`, `stok` dec, `stok_min` dec, `hpp`, `aktif`, soft delete |
| `sparepart_satuans` | `sparepart_id`, `satuan_id`, `isi` dec, `harga_jual`, `barcode`? (unique), `aktif`; unique (`sparepart_id`, `satuan_id`) |
| `suppliers` | `nama`, `kontak`?, `telepon`?, `alamat`?, `catatan`?, soft delete |
| `kategori_jasas` | `nama` |
| `jasas` | `nama`, `kategori_id`, `harga_standar`, soft delete |
| `mekaniks` | `nama`, `telepon`?, `komisi_persen`, `batas_kasbon`, `aktif`, soft delete |
| `pelanggans` | `nama`, `telepon`?, `alamat`?, soft delete |
| `kendaraans` | `plat` (unique), `pelanggan_id`?, `merk`?, `tipe`?, `tahun`?, `warna`?, `no_rangka`?, soft delete. Hanya plat yang wajib: pemilik, merk, dan tipe boleh kosong dan dilengkapi nanti |

### Transaksi penjualan / servis
| Tabel | Kolom utama |
|---|---|
| `transaksis` | `nomor` (unique), `jenis` (servis/penjualan), `kendaraan_id`?, `pelanggan_id`?, `user_id`, `keluhan`?, `km`?, `status`, `total_jasa`, `total_sparepart`, `total_diskon`, `total`, `total_komisi`, `total_hpp`, `metode_bayar`?, `dibayar`?, `kembalian`?, `lunas_at`?, `batal_at`?, `batal_user_id`?, `batal_alasan`? |
| `transaksi_jasas` | `transaksi_id`, `jasa_id`?, `nama`, `harga`, `diskon`, `mekanik_id`, `komisi_persen`, `komisi` |
| `transaksi_spareparts` | `transaksi_id`, `sparepart_id`, `nama`, `satuan`, `isi` dec, `qty` dec, `qty_dasar` dec, `harga`, `diskon`, `hpp` |

### Pembelian
| Tabel | Kolom utama |
|---|---|
| `pembelians` | `nomor` (unique), `supplier_id`, `no_faktur`?, `tanggal_faktur`, `user_id`, `status` (draft/diterima/batal), `total`, `status_bayar` (lunas/tempo), `jatuh_tempo`?, `dibayar_at`?, `metode_bayar`?, `sumber`? (laci/luar), `diterima_at`?, `batal_at`?, `batal_user_id`?, `batal_alasan`?, `catatan`? |
| `pembelian_items` | `pembelian_id`, `sparepart_id`, `satuan`, `isi` dec, `qty` dec, `qty_dasar` dec, `harga`, `diskon`, `subtotal` |

### Buku mutasi
| Tabel | Kolom utama |
|---|---|
| `stok_mutasis` | `sparepart_id`, `qty` dec (+/−, satuan dasar), `jenis` (awal/masuk/keluar/kembali/batal_masuk/penyesuaian), `transaksi_id`?, `pembelian_id`?, `user_id`, `keterangan`? |
| `saldo_mutasis` | `mekanik_id`, `tanggal`, `jumlah` (+/−), `jenis` (komisi/koreksi/kasbon), `transaksi_id`?, `metode`? dan `sumber`? (laci/luar, untuk kasbon), `pencairan_id`?, `user_id`, `keterangan`? |

### Pencairan komisi
| Tabel | Kolom utama |
|---|---|
| `pencairans` | `nomor` (unique, `KM-YYYYMMDD-NNN`), `mekanik_id`, `periode_dari`, `periode_sampai`, `total_komisi`, `total_kasbon`, `total_koreksi`, `dibayar`, `metode` (tunai/transfer), `sumber` (laci/luar), `user_id`, `catatan`?, `batal_at`?, `batal_user_id`?, `batal_alasan`? |

Kolom `total_*` adalah cache yang dihitung ulang dari baris setiap kali baris berubah, supaya laporan cepat.

Index: `transaksis(status, created_at)`, `transaksis(lunas_at)`, `pembelians(status, tanggal_faktur)`, `pembelians(status_bayar, jatuh_tempo)`, `spareparts(nama)`, `stok_mutasis(sparepart_id, created_at)`, `saldo_mutasis(mekanik_id, pencairan_id, tanggal)`, `pencairans(mekanik_id, periode_sampai)`.

## 9. Halaman (rute SPA)
Rute di bawah adalah rute Vue Router. Setiap rute punya `meta.roles`; *navigation guard* mengarahkan ke `/login` bila belum login, dan ke halaman awal peran bila tidak berhak. Pengecekan peran yang sebenarnya tetap di API.

| Area | Rute | Peran | Isi |
|---|---|---|---|
| Login | `/login` | semua | |
| Dashboard | `/` | owner, kasir | omzet hari ini, transaksi terbuka, stok menipis, tempo supplier jatuh tempo; owner: laba kotor, total saldo mekanik |
| Master | `/master/{...}` | owner (pelanggan/kendaraan juga kasir) | CRUD generik dari `config/master.php` |
| Sparepart | `/sparepart`, `/sparepart/{id}` | owner | form sparepart + tabel satuan dalam satu halaman |
| Papan Servis | `/servis` | owner, kasir | motor di bengkel per tahap, kotak "motor masuk" (cukup plat) |
| Kasir Sparepart | `/kasir` | owner, kasir | POS penjualan langsung: grid barang + keranjang |
| Transaksi | `/transaksi`, `/transaksi/servis/baru` (form lengkap), `/transaksi/{id}` | owner, kasir | daftar (filter status/tanggal), form, detail, bayar, void |
| Nota | `/cetak/nota/{id}` | owner, kasir | tanpa layout aplikasi, CSS print (58/80 mm atau A5), buka di tab baru lalu `window.print()` |
| Penerimaan barang | `/pembelian`, `/pembelian/baru`, `/pembelian/{id}` | owner, kasir | daftar, form, terima, bayar tempo, void (owner) |
| Stok | `/stok`, `/stok/{sparepart}` | owner, kasir | daftar stok, kartu stok, penyesuaian (owner) |
| Riwayat servis | `/riwayat?plat=` | owner, kasir | riwayat per kendaraan, KM terakhir |
| Pekerjaan saya | `/mekanik/pekerjaan`, `/mekanik/pekerjaan/{id}` | mekanik | daftar dan detail, ubah status |
| Saldo saya | `/mekanik/saldo` | mekanik | baris belum cair, riwayat pencairan, slip |
| Saldo mekanik | `/saldo`, `/saldo/{mekanik}` | owner | saldo semua mekanik, baris belum cair, kasbon, cairkan per periode |
| Pencairan | `/pencairan`, `/pencairan/{id}` | owner | riwayat pencairan (filter mekanik/tanggal), detail, void |
| Slip komisi | `/cetak/slip/{id}` | owner, mekanik (milik sendiri) | tanpa layout aplikasi, CSS print |
| Laporan | `/laporan` | owner | rentang tanggal: omzet, laba kotor, komisi per mekanik, sparepart terlaris, pembelian per supplier |
| Rekap kas | `/laporan/kas` | owner, kasir (hari ini) | per hari: uang masuk per metode bayar, uang keluar dari laci (kasbon, pencairan, bayar supplier tunai), saldo laci |
| Pengaturan | `/pengaturan` | owner | data bengkel, nota, tampilan (tema, warna, mode) |

Mekanik yang login langsung diarahkan ke `/mekanik/pekerjaan`. Halaman mekanik dibuat ramah HP.

### 9.1 API
- Semua endpoint di `/api`, dilindungi `auth:sanctum` + middleware `role:...`. Hanya `POST /api/login` yang terbuka.
- Login: SPA memanggil `GET /sanctum/csrf-cookie` lalu `POST /api/login`. `GET /api/me` mengembalikan user, peran, dan pengaturan (nama bengkel, tampilan).
- Pola endpoint mengikuti halaman, misalnya `GET/POST /api/transaksi`, `GET /api/transaksi/{id}`, `POST /api/transaksi/{id}/jasa`, `POST /api/transaksi/{id}/bayar`, `POST /api/transaksi/{id}/batal`.
- Daftar memakai paginasi Laravel (`?page=&per_page=&q=&sort=`) yang langsung cocok dengan `DataTable` lazy.
- Respons sukses `{ data, message? }`. Error validasi pakai format bawaan Laravel (422 + `errors`) dan ditampilkan per field di form. 401 membuat SPA kembali ke `/login`; 403 menampilkan Toast.
- Pencarian cepat untuk AutoComplete: `GET /api/cari/{kendaraan|sparepart|jasa|supplier}?q=` (maks 20 hasil).

## 10. Komponen PrimeVue yang dipakai
| Kebutuhan | Komponen |
|---|---|
| Tabel (cari, sortir, paginasi server-side) | `DataTable` lazy + `Column` |
| Baris transaksi/pembelian yang bisa diedit | `DataTable` dengan `editMode="cell"` |
| Input rupiah | `InputNumber mode="currency" currency="IDR" locale="id-ID"` |
| Input qty desimal | `InputNumber :maxFractionDigits="2"` |
| Cari kendaraan / sparepart / jasa / supplier | `AutoComplete` (query ke `/api/cari/...`) |
| Pilih satuan, mekanik, metode bayar | `Select`, `SelectButton` |
| Form dialog (bayar, void, pencairan, penyesuaian) | `Dialog` |
| Konfirmasi | `ConfirmDialog` + `useConfirm` |
| Notifikasi | `Toast` (dari `message` respons API) |
| Badge status | `Tag` |
| Kartu statistik | `Card` |
| Menu samping | `PanelMenu` di layout (disaring per peran) |
| Tanggal | `DatePicker` (rentang untuk laporan) |
| Upload logo | `FileUpload` mode basic |
| Pilih tema, mode | `SelectButton` |
| Pilih warna utama | swatch bulat + `<input type="color">` untuk warna brand |

Komponen di-import otomatis lewat `@primevue/auto-import-resolver` (unplugin-vue-components) supaya bundle kecil.

## 11. Struktur kode
```
backend/                          Laravel 12 (API)
  app/Models/                     Transaksi, TransaksiJasa, TransaksiSparepart, Pembelian, PembelianItem,
                                  Sparepart, SparepartSatuan, Satuan, Supplier, Mekanik, Kendaraan,
                                  Pelanggan, Jasa, StokMutasi, SaldoMutasi, Pencairan, User, Pengaturan
  app/Services/                   TransaksiService (baris, status, bayar, void)
                                  PembelianService (terima, bayar tempo, void)
                                  StokService (keluar, kembali, masuk + HPP rata-rata, penyesuaian)
                                  SaldoService (komisi, koreksi, kasbon, pratinjau & cairkan periode, void)
  app/Http/Controllers/Api/       Auth, Dashboard, Master, Sparepart, Transaksi, Pembelian, Stok, Riwayat,
                                  Saldo, Pencairan, Laporan, Pengaturan, Cari, Mekanik/{Pekerjaan,Saldo}
  app/Http/Requests/              validasi per aksi (BayarRequest, TerimaPembelianRequest, ...)
  app/Http/Resources/             bentuk JSON (menyembunyikan HPP/laba untuk mekanik)
  app/Http/Middleware/            Role
  app/Console/Commands/           db:backup
  config/master.php               definisi CRUD master (field, aturan validasi, relasi)
  routes/api.php                  semua endpoint
  routes/web.php                  catch-all → public/spa/index.html
  public/spa/                     hasil build frontend (tidak diedit manual)
  tests/Feature/                  Auth, Role, Master, Satuan, TransaksiServis, Penjualan, Pembelian,
                                  Hpp, Stok, Saldo, Kasbon, Pencairan, Void, Mekanik, Laporan

frontend/                         Vue 3 SPA
  vite.config.js                  plugin vue + PrimeVueResolver, alias @, proxy /api & /sanctum ke
                                  Laravel saat dev, build.outDir ../backend/public/spa, base /spa/
  src/main.js                     Vue + Router + Pinia + PrimeVue (preset dari useAppTheme,
                                  darkModeSelector '.app-dark', locale id) + ToastService + ConfirmationService
  src/api/http.js                 axios baseURL /api, withCredentials, 401 → logout, helper get/post/put/del, errMsg
  src/router/index.js             rute + meta.roles + guard login/peran
  src/stores/                     auth (user, peran, login/logout/me), pengaturan
  src/composables/useAppTheme.js  daftar tema & warna, buildPalette(hex), contrastFor(hex),
                                  initTheme(), applyTheme() via config.theme, mode gelap (.app-dark)
  src/layouts/                    AppLayout.vue (sidebar per peran, topbar, Toast, ConfirmDialog),
                                  MekanikLayout.vue (ramah HP, menu bawah), PrintLayout.vue (kosong)
  src/views/                      Login, Dashboard, Master, Sparepart{List,Form},
                                  Transaksi{List,Form,Detail}, Pembelian{List,Form,Detail},
                                  Stok{List,Kartu}, Riwayat, Saldo{List,Detail}, Pencairan{List,Detail},
                                  Laporan, RekapKas, Pengaturan, mekanik/{Pekerjaan,PekerjaanDetail,Saldo},
                                  cetak/{Nota,Slip}
  src/components/                 PageHeader, StatCard, FormMaster (render field dari /api/master/{x}/meta),
                                  PilihSparepart (AutoComplete + pilih satuan + cek stok)
  src/utils/                      format (rupiah, tanggal, qty), storage (localStorage aman try/catch)
```
- Semua hitungan uang, stok, HPP, dan komisi ada di `app/Services` dan dijalankan di dalam `DB::transaction`. Controller hanya validasi lalu memanggil service. Frontend **tidak** menghitung total final; angka yang tampil saat mengetik hanya pratinjau, angka resmi dari respons API.
- Semua aturan di §5 dan §7 punya tes Feature. Logika frontend yang tidak sepele (palet warna, format) punya tes Vitest.

## 12. Operasional
- **Backup:** `php artisan db:backup` (`mysqldump` ke `storage/backups`), terjadwal harian 23:30, simpan 14 terbaru. Owner bisa unduh backup dari dashboard.
- **Deploy lokal:** Laragon/XAMPP; buka lewat `http://bengkel.test` di PC kasir. Mekanik membuka dari HP lewat jaringan Wi-Fi bengkel (IP PC kasir).
- **Dev:** `php artisan serve` (backend) + `npm run dev` di `frontend/` (Vite proxy ke Laravel).
- **Build:** `npm run build` di `frontend/` → `backend/public/spa`. Folder `frontend/` tidak perlu di-upload.
- **Deploy shared hosting:** upload isi `backend/` (termasuk `public/spa` dan `vendor`), arahkan document root ke `public/`, isi `SANCTUM_STATEFUL_DOMAINS` dan `SESSION_DOMAIN` sesuai domain, cron `* * * * * php artisan schedule:run`.
- **Deploy lokal:** domain/IP yang dipakai HP mekanik (misalnya `192.168.1.10`) juga harus masuk `SANCTUM_STATEFUL_DOMAINS`, kalau tidak login dari HP gagal.
- **Cetak nota dan slip:** halaman cetak SPA dibuka di tab baru lalu `window.print()`, tanpa akses printer dari server.

### 12.1 Catatan shared hosting
| Kendala umum | Penanganan |
|---|---|
| Versi PHP | Pilih PHP 8.2+ di cPanel (MultiPHP / Select PHP Version), aktifkan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` |
| Tidak ada SSH / Composer | `composer install --no-dev --optimize-autoloader` di lokal, upload `vendor/` sekalian |
| Migrasi tanpa SSH | Perintah `php artisan migrate --force` dijadwalkan sekali lewat cron, atau pakai Terminal cPanel bila ada |
| `exec()` / `mysqldump` dimatikan | `db:backup` memakai dumper PHP murni (`ifsnop/mysqldump-php`), bukan `mysqldump` |
| `storage:link` (symlink) gagal | Logo disimpan di `pengaturans` sebagai data URL (diperkecil di browser, maks ~200 KB), tanpa folder upload |
| Document root tidak bisa diubah | Isi `public/` ditaruh di `public_html/`, sisa Laravel di luar `public_html`, path di `index.php` disesuaikan |
| Session | Driver `file` (bawaan), `SESSION_SECURE_COOKIE=true` bila pakai HTTPS |
- **Data awal:** seeder membuat satu owner, satuan umum, kategori sparepart, dan kategori jasa. Password owner wajib diganti saat login pertama. Stok awal diisi lewat penyesuaian (jenis `awal`).

## 13. Fase 2
- Bon/piutang pelanggan dan bayar sebagian.
- Retur ke supplier dan retur dari pelanggan.
- Stok opname (hitung fisik massal + selisih otomatis).
- Pesanan pembelian (PO) sebelum barang datang.
- Servis garansi / servis ulang (jasa gratis, tertaut ke transaksi asal).
- Reminder servis berkala lewat `wa.me` berdasarkan tanggal/KM terakhir.
- Pengeluaran operasional, supaya laporan menampilkan laba bersih.
- Cetak ESC/POS langsung ke printer thermal (butuh agen lokal).

## 14. Keputusan
| Topik | Keputusan | Catatan |
|---|---|---|
| Dasar komisi | Jasa **setelah** diskon | Pasti |
| Persen komisi | **Per mekanik**, disalin ke tiap baris jasa | Pasti |
| Harga pelanggan grosir | **Tidak ada.** Harga jual hanya per satuan (harga box sendiri) | Pasti |
| Tampilan | Tema, warna utama, dan mode bisa diatur owner (§4.1) | Pasti |
| Oli eceran | Belum diketahui. Satuan dasar oli ditentukan per item saat input master (liter atau botol); qty desimal mendukung keduanya | Tidak perlu ubah kode |
| Bayar supplier | Belum diketahui. Lunas **dan** tempo sama-sama didukung | Sudah di §6.3 |
| Ukuran nota | Belum diketahui. Dipilih di Pengaturan (58 mm / 80 mm / A5), default **58 mm** | Ganti kapan saja tanpa ubah kode |
| Perangkat mekanik | HP masing-masing | Halaman mekanik wajib nyaman di layar HP; HP harus satu Wi-Fi dengan PC kasir (deploy lokal) atau pakai hosting |
