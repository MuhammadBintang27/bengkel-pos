<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Rak jadi master sendiri. Teks lokasi yang sudah ada dipindahkan menjadi rak (tanpa awalan "Rak "). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raks', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 40)->unique();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::table('spareparts', function (Blueprint $table) {
            $table->foreignId('rak_id')->nullable()->after('kompatibel')->constrained('raks');
        });

        foreach (DB::table('spareparts')->whereNotNull('lokasi')->where('lokasi', '!=', '')->distinct()->pluck('lokasi') as $lokasi) {
            $nama = trim(preg_replace('/^rak\s+/i', '', $lokasi));
            if ($nama === '') {
                continue;
            }
            $id = DB::table('raks')->where('nama', $nama)->value('id')
                ?? DB::table('raks')->insertGetId(['nama' => $nama, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('spareparts')->where('lokasi', $lokasi)->update(['rak_id' => $id]);
        }

        Schema::table('spareparts', fn (Blueprint $table) => $table->dropColumn('lokasi'));
    }

    public function down(): void
    {
        Schema::table('spareparts', fn (Blueprint $table) => $table->string('lokasi', 40)->nullable()->after('kompatibel'));

        foreach (DB::table('raks')->get() as $rak) {
            DB::table('spareparts')->where('rak_id', $rak->id)->update(['lokasi' => $rak->nama]);
        }

        Schema::table('spareparts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rak_id');
        });
        Schema::dropIfExists('raks');
    }
};
