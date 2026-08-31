<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cooling_tuneles', function (Blueprint $table) {
            $table->foreignId('bodega_id')->nullable()->after('id')
                  ->constrained('pre_cooling_bodegas')->nullOnDelete();
        });

        Schema::table('pre_cooling_camaras', function (Blueprint $table) {
            $table->foreignId('bodega_id')->nullable()->after('id')
                  ->constrained('pre_cooling_bodegas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pre_cooling_tuneles', function (Blueprint $table) {
            $table->dropForeign(['bodega_id']);
            $table->dropColumn('bodega_id');
        });
        Schema::table('pre_cooling_camaras', function (Blueprint $table) {
            $table->dropForeign(['bodega_id']);
            $table->dropColumn('bodega_id');
        });
    }
};
