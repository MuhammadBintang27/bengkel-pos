<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturans', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 50)->unique();
            $table->longText('nilai')->nullable(); // logo disimpan sebagai data URL
            $table->timestamps();
        });

        Schema::create('satuans', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 30)->unique();
            $table->timestamps();
        });

        Schema::create('kategori_spareparts', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 60)->unique();
            $table->timestamps();
        });

        Schema::create('spareparts', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->unique();
            $table->string('part_number', 60)->nullable();
            $table->string('nama');
            $table->foreignId('kategori_id')->constrained('kategori_spareparts');
            $table->string('merk', 60)->nullable();
            $table->string('kompatibel')->nullable();
            $table->string('lokasi', 40)->nullable();
            $table->foreignId('satuan_dasar_id')->constrained('satuans');
            $table->decimal('stok', 12, 2)->default(0);
            $table->decimal('stok_min', 12, 2)->default(0);
            $table->unsignedBigInteger('hpp')->default(0); // per satuan dasar
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('nama');
        });

        Schema::create('sparepart_satuans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sparepart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('satuan_id')->constrained('satuans');
            $table->decimal('isi', 12, 2);
            $table->unsignedBigInteger('harga_jual');
            $table->string('barcode', 60)->nullable()->unique();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->unique(['sparepart_id', 'satuan_id']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kontak', 100)->nullable();
            $table->string('telepon', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('kategori_jasas', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 60)->unique();
            $table->timestamps();
        });

        Schema::create('jasas', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->foreignId('kategori_id')->constrained('kategori_jasas');
            $table->unsignedBigInteger('harga_standar')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mekaniks', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('telepon', 30)->nullable();
            $table->decimal('komisi_persen', 5, 2)->default(0);
            $table->unsignedBigInteger('batas_kasbon')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('mekanik_id')->references('id')->on('mekaniks');
        });

        Schema::create('pelanggans', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('telepon', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('kendaraans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelanggan_id')->constrained();
            $table->string('plat', 15)->unique();
            $table->string('merk', 40);
            $table->string('tipe', 60);
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->string('warna', 30)->nullable();
            $table->string('no_rangka', 40)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['mekanik_id']));
        foreach (['kendaraans', 'pelanggans', 'mekaniks', 'jasas', 'kategori_jasas', 'suppliers',
            'sparepart_satuans', 'spareparts', 'kategori_spareparts', 'satuans', 'pengaturans'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
