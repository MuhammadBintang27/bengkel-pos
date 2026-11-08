<?php

use App\Models\Jasa;
use App\Models\KategoriJasa;
use App\Models\KategoriSparepart;
use App\Models\Kendaraan;
use App\Models\Mekanik;
use App\Models\Pelanggan;
use App\Models\Rak;
use App\Models\Satuan;
use App\Models\Supplier;
use App\Models\User;

/*
| Definisi CRUD master generik (dipakai MasterController dan halaman Master di frontend).
|
| fields[]: key, label, type (text|textarea|number|money|percent|select|switch|password|plat|tel),
|           rules (aturan validasi Laravel; {id} diganti id record saat edit), table (tampil di tabel),
|           options (select: ['model' => X, 'label' => 'nama'] atau daftar statis [value => label]),
|           show (path tampilan di tabel, mis. 'pelanggan.nama'), hint, placeholder, default.
| roles: peran selain owner yang boleh mengelola.
| Sparepart punya halaman sendiri karena ada tabel satuan.
*/

return [
    'pelanggan' => [
        'model' => Pelanggan::class,
        'label' => 'Pelanggan',
        'desc' => 'Pemilik kendaraan yang servis di bengkel.',
        'icon' => 'pelanggan',
        'roles' => ['kasir'],
        'search' => ['nama', 'telepon'],
        'opsi_extra' => 'telepon',
        'sort' => 'nama',
        'withCount' => ['kendaraans'],
        'fields' => [
            ['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'table' => true],
            ['key' => 'telepon', 'label' => 'Telepon / WhatsApp', 'type' => 'tel', 'rules' => ['nullable', 'string', 'max:30'], 'table' => true],
            ['key' => 'alamat', 'label' => 'Alamat', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:255']],
        ],
        'extraColumns' => [['key' => 'kendaraans_count', 'label' => 'Kendaraan', 'type' => 'number']],
    ],

    'kendaraan' => [
        'model' => Kendaraan::class,
        'label' => 'Kendaraan',
        'desc' => 'Motor pelanggan. Plat nomor disimpan tanpa spasi agar riwayat tidak terpecah.',
        'icon' => 'motor',
        'roles' => ['kasir'],
        'search' => ['plat', 'merk', 'tipe', 'pelanggan.nama'],
        'opsi_label' => 'plat',
        'opsi_extra' => 'tipe',
        'sort' => 'plat',
        'with' => ['pelanggan:id,nama,telepon'],
        'fields' => [
            ['key' => 'plat', 'label' => 'Plat nomor', 'type' => 'plat', 'rules' => ['required', 'string', 'max:15', 'unique:kendaraans,plat,{id}'], 'table' => true, 'placeholder' => 'B 1234 XYZ'],
            ['key' => 'pelanggan_id', 'label' => 'Pemilik', 'type' => 'select', 'options' => ['model' => Pelanggan::class, 'label' => 'nama', 'extra' => 'telepon'], 'rules' => ['nullable', 'exists:pelanggans,id'], 'table' => true, 'show' => 'pelanggan.nama', 'hint' => 'Boleh dikosongkan, bisa dilengkapi nanti.'],
            ['key' => 'merk', 'label' => 'Merk', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:40'], 'table' => true, 'suggest' => ['Honda', 'Yamaha', 'Suzuki', 'Kawasaki', 'TVS', 'Vespa']],
            ['key' => 'tipe', 'label' => 'Tipe', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:60'], 'table' => true, 'placeholder' => 'Vario 125, NMAX, Beat'],
            ['key' => 'tahun', 'label' => 'Tahun', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1970', 'max:2100'], 'table' => true],
            ['key' => 'warna', 'label' => 'Warna', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:30']],
            ['key' => 'no_rangka', 'label' => 'No. rangka', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:40']],
        ],
    ],

    'jasa' => [
        'model' => Jasa::class,
        'label' => 'Jasa',
        'desc' => 'Daftar jasa servis. Harga standar jadi acuan dan bisa diubah saat transaksi.',
        'icon' => 'servis',
        'search' => ['nama', 'kategori.nama'],
        'sort' => 'nama',
        'with' => ['kategori:id,nama'],
        'fields' => [
            ['key' => 'nama', 'label' => 'Nama jasa', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'table' => true],
            ['key' => 'kategori_id', 'label' => 'Kategori', 'type' => 'select', 'options' => ['model' => KategoriJasa::class, 'label' => 'nama'], 'rules' => ['required', 'exists:kategori_jasas,id'], 'table' => true, 'show' => 'kategori.nama'],
            ['key' => 'harga_standar', 'label' => 'Harga standar', 'type' => 'money', 'rules' => ['required', 'integer', 'min:0', 'max:100000000'], 'table' => true],
        ],
    ],

    'mekanik' => [
        'model' => Mekanik::class,
        'label' => 'Mekanik',
        'desc' => 'Persen komisi dihitung dari jasa setelah diskon. Akun login dibuat di menu Pengguna.',
        'icon' => 'mekanik',
        'search' => ['nama', 'telepon'],
        'sort' => 'nama',
        'with' => ['user:id,mekanik_id,username'],
        'fields' => [
            ['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'table' => true],
            ['key' => 'telepon', 'label' => 'Telepon / WhatsApp', 'type' => 'tel', 'rules' => ['nullable', 'string', 'max:30'], 'table' => true],
            ['key' => 'komisi_persen', 'label' => 'Komisi jasa', 'type' => 'percent', 'rules' => ['required', 'numeric', 'min:0', 'max:100'], 'table' => true, 'hint' => 'Perubahan hanya berlaku untuk transaksi baru.'],
            ['key' => 'batas_kasbon', 'label' => 'Batas kasbon', 'type' => 'money', 'rules' => ['required', 'integer', 'min:0'], 'table' => true, 'hint' => 'Isi 0 bila mekanik tidak boleh kasbon.', 'default' => 0],
            ['key' => 'aktif', 'label' => 'Aktif', 'type' => 'switch', 'rules' => ['boolean'], 'table' => true, 'default' => true, 'hint' => 'Mekanik nonaktif tidak bisa dipilih di transaksi baru.'],
        ],
        'extraColumns' => [['key' => 'user.username', 'label' => 'Akun login', 'type' => 'text']],
    ],

    'supplier' => [
        'model' => Supplier::class,
        'label' => 'Supplier',
        'desc' => 'Toko atau distributor tempat bengkel membeli sparepart.',
        'icon' => 'truk',
        'search' => ['nama', 'kontak', 'telepon'],
        'sort' => 'nama',
        'fields' => [
            ['key' => 'nama', 'label' => 'Nama supplier', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'table' => true],
            ['key' => 'kontak', 'label' => 'Nama kontak / sales', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100'], 'table' => true],
            ['key' => 'telepon', 'label' => 'Telepon / WhatsApp', 'type' => 'tel', 'rules' => ['nullable', 'string', 'max:30'], 'table' => true],
            ['key' => 'alamat', 'label' => 'Alamat', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:255']],
            ['key' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500'], 'placeholder' => 'Mis. tempo 30 hari, kirim tiap Senin'],
        ],
    ],

    'kategori-sparepart' => [
        'model' => KategoriSparepart::class,
        'label' => 'Kategori Sparepart',
        'icon' => 'sparepart',
        'search' => ['nama'],
        'sort' => 'nama',
        'withCount' => ['spareparts'],
        'fields' => [
            ['key' => 'nama', 'label' => 'Nama kategori', 'type' => 'text', 'rules' => ['required', 'string', 'max:60', 'unique:kategori_spareparts,nama,{id}'], 'table' => true],
        ],
        'extraColumns' => [['key' => 'spareparts_count', 'label' => 'Jumlah sparepart', 'type' => 'number']],
    ],

    'kategori-jasa' => [
        'model' => KategoriJasa::class,
        'label' => 'Kategori Jasa',
        'icon' => 'servis',
        'search' => ['nama'],
        'sort' => 'nama',
        'withCount' => ['jasas'],
        'fields' => [
            ['key' => 'nama', 'label' => 'Nama kategori', 'type' => 'text', 'rules' => ['required', 'string', 'max:60', 'unique:kategori_jasas,nama,{id}'], 'table' => true],
        ],
        'extraColumns' => [['key' => 'jasas_count', 'label' => 'Jumlah jasa', 'type' => 'number']],
    ],

    'rak' => [
        'model' => Rak::class,
        'label' => 'Rak',
        'desc' => 'Tempat penyimpanan barang di bengkel. Namanya tampil di kasir supaya mudah mengambil barang.',
        'icon' => 'stok',
        'search' => ['nama', 'keterangan'],
        'sort' => 'nama',
        'withCount' => ['spareparts'],
        'fields' => [
            ['key' => 'nama', 'label' => 'Nama / kode rak', 'type' => 'text', 'rules' => ['required', 'string', 'max:40', 'unique:raks,nama,{id}'], 'table' => true, 'placeholder' => 'A1, B2, Lemari Oli'],
            ['key' => 'keterangan', 'label' => 'Keterangan', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255'], 'table' => true, 'placeholder' => 'Mis. Rak oli dekat pintu'],
        ],
        'extraColumns' => [['key' => 'spareparts_count', 'label' => 'Jumlah barang', 'type' => 'number']],
    ],

    'satuan' => [
        'model' => Satuan::class,
        'label' => 'Satuan',
        'icon' => 'stok',
        'search' => ['nama'],
        'sort' => 'nama',
        'fields' => [
            ['key' => 'nama', 'label' => 'Nama satuan', 'type' => 'text', 'rules' => ['required', 'string', 'max:30', 'unique:satuans,nama,{id}'], 'table' => true, 'placeholder' => 'pcs, box, liter'],
        ],
    ],

    'pengguna' => [
        'model' => User::class,
        'label' => 'Pengguna',
        'desc' => 'Akun login untuk owner, kasir, dan mekanik.',
        'icon' => 'pelanggan',
        'search' => ['nama', 'username'],
        'sort' => 'nama',
        'with' => ['mekanik:id,nama'],
        'fields' => [
            ['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'table' => true],
            ['key' => 'username', 'label' => 'Username', 'type' => 'text', 'rules' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username,{id}'], 'table' => true, 'hint' => 'Huruf, angka, - atau _ tanpa spasi.'],
            ['key' => 'role', 'label' => 'Peran', 'type' => 'select', 'options' => ['owner' => 'Owner', 'kasir' => 'Kasir', 'mekanik' => 'Mekanik'], 'rules' => ['required', 'in:owner,kasir,mekanik'], 'table' => true],
            ['key' => 'mekanik_id', 'label' => 'Data mekanik', 'type' => 'select', 'options' => ['model' => Mekanik::class, 'label' => 'nama'], 'rules' => ['nullable', 'required_if:role,mekanik', 'exists:mekaniks,id', 'unique:users,mekanik_id,{id}'], 'table' => true, 'show' => 'mekanik.nama', 'hint' => 'Wajib untuk peran mekanik.', 'when' => ['role' => 'mekanik']],
            ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'rules' => ['nullable', 'string', 'min:6'], 'hint' => 'Kosongkan saat edit bila tidak diganti. Pengguna diminta ganti password saat login pertama.'],
            ['key' => 'aktif', 'label' => 'Aktif', 'type' => 'switch', 'rules' => ['boolean'], 'table' => true, 'default' => true, 'hint' => 'Akun nonaktif tidak bisa login.'],
        ],
    ],
];
