<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bengkel sering hanya mencatat plat: pemilik, merk, dan tipe boleh dilengkapi nanti. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kendaraans', function (Blueprint $table) {
            $table->unsignedBigInteger('pelanggan_id')->nullable()->change();
            $table->string('merk', 40)->nullable()->change();
            $table->string('tipe', 60)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('kendaraans', function (Blueprint $table) {
            $table->unsignedBigInteger('pelanggan_id')->nullable(false)->change();
            $table->string('merk', 40)->nullable(false)->change();
            $table->string('tipe', 60)->nullable(false)->change();
        });
    }
};
