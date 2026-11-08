<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksis', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 20)->unique();
            $table->string('jenis', 10); // servis, penjualan
            $table->foreignId('kendaraan_id')->nullable()->constrained();
            $table->foreignId('pelanggan_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->text('keluhan')->nullable();
            $table->unsignedInteger('km')->nullable();
            $table->string('status', 12)->default('antri'); // antri, dikerjakan, selesai, lunas, batal
            $table->unsignedBigInteger('total_jasa')->default(0);
            $table->unsignedBigInteger('total_sparepart')->default(0);
            $table->unsignedBigInteger('total_diskon')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('total_komisi')->default(0);
            $table->unsignedBigInteger('total_hpp')->default(0);
            $table->string('metode_bayar', 10)->nullable(); // tunai, transfer, qris
            $table->unsignedBigInteger('dibayar')->nullable();
            $table->unsignedBigInteger('kembalian')->nullable();
            $table->timestamp('lunas_at')->nullable()->index();
            $table->timestamp('batal_at')->nullable();
            $table->foreignId('batal_user_id')->nullable()->constrained('users');
            $table->string('batal_alasan')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('transaksi_jasas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jasa_id')->nullable()->constrained();
            $table->string('nama');
            $table->unsignedBigInteger('harga');
            $table->unsignedBigInteger('diskon')->default(0);
            $table->foreignId('mekanik_id')->constrained();
            $table->decimal('komisi_persen', 5, 2);
            $table->unsignedBigInteger('komisi');
            $table->timestamps();
        });

        Schema::create('transaksi_spareparts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sparepart_id')->constrained();
            $table->string('nama');
            $table->string('satuan', 30);
            $table->decimal('isi', 12, 2);
            $table->decimal('qty', 12, 2);
            $table->decimal('qty_dasar', 12, 2);
            $table->unsignedBigInteger('harga');
            $table->unsignedBigInteger('diskon')->default(0);
            $table->unsignedBigInteger('hpp');
            $table->timestamps();
        });

        Schema::create('pembelians', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 20)->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->string('no_faktur', 60)->nullable();
            $table->date('tanggal_faktur');
            $table->foreignId('user_id')->constrained();
            $table->string('status', 10)->default('draft'); // draft, diterima, batal
            $table->unsignedBigInteger('total')->default(0);
            $table->string('status_bayar', 10)->default('lunas'); // lunas, tempo
            $table->date('jatuh_tempo')->nullable();
            $table->timestamp('dibayar_at')->nullable();
            $table->string('metode_bayar', 10)->nullable();
            $table->string('sumber', 10)->nullable(); // laci, luar
            $table->timestamp('diterima_at')->nullable();
            $table->timestamp('batal_at')->nullable();
            $table->foreignId('batal_user_id')->nullable()->constrained('users');
            $table->string('batal_alasan')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index(['status', 'tanggal_faktur']);
            $table->index(['status_bayar', 'jatuh_tempo']);
        });

        Schema::create('pembelian_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembelian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sparepart_id')->constrained();
            $table->string('satuan', 30);
            $table->decimal('isi', 12, 2);
            $table->decimal('qty', 12, 2);
            $table->decimal('qty_dasar', 12, 2);
            $table->unsignedBigInteger('harga');
            $table->unsignedBigInteger('diskon')->default(0);
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });

        Schema::create('pencairans', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 20)->unique();
            $table->foreignId('mekanik_id')->constrained();
            $table->date('periode_dari');
            $table->date('periode_sampai');
            $table->unsignedBigInteger('total_komisi');
            $table->unsignedBigInteger('total_kasbon');
            $table->unsignedBigInteger('total_koreksi');
            $table->unsignedBigInteger('dibayar');
            $table->string('metode', 10);
            $table->string('sumber', 10);
            $table->foreignId('user_id')->constrained();
            $table->text('catatan')->nullable();
            $table->timestamp('batal_at')->nullable();
            $table->foreignId('batal_user_id')->nullable()->constrained('users');
            $table->string('batal_alasan')->nullable();
            $table->timestamps();
            $table->index(['mekanik_id', 'periode_sampai']);
        });

        Schema::create('saldo_mutasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mekanik_id')->constrained();
            $table->date('tanggal');
            $table->bigInteger('jumlah');
            $table->string('jenis', 10); // komisi, koreksi, kasbon
            $table->foreignId('transaksi_id')->nullable()->constrained();
            $table->string('metode', 10)->nullable();
            $table->string('sumber', 10)->nullable();
            $table->foreignId('pencairan_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('keterangan')->nullable();
            $table->timestamps();
            $table->index(['mekanik_id', 'pencairan_id', 'tanggal']);
        });

        Schema::create('stok_mutasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sparepart_id')->constrained();
            $table->decimal('qty', 12, 2); // +/− dalam satuan dasar
            $table->string('jenis', 15); // awal, masuk, keluar, kembali, batal_masuk, penyesuaian
            $table->foreignId('transaksi_id')->nullable()->constrained();
            $table->foreignId('pembelian_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('keterangan')->nullable();
            $table->timestamps();
            $table->index(['sparepart_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['stok_mutasis', 'saldo_mutasis', 'pencairans', 'pembelian_items', 'pembelians',
            'transaksi_spareparts', 'transaksi_jasas', 'transaksis'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
