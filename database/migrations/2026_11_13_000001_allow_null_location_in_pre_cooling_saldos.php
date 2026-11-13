<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cooling_saldos', function (Blueprint $table) {
            $table->dropUnique('pcs_camara_slot_unique');
            $table->string('banda', 50)->nullable()->change();
            $table->string('fila', 50)->nullable()->change();
            $table->string('columna', 50)->nullable()->change();
            $table->string('altura', 50)->nullable()->change();
            $table->string('nivel', 50)->nullable()->change();
            $table->unique(
                ['camara_id', 'banda', 'fila', 'columna', 'altura', 'nivel'],
                'pcs_camara_slot_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pre_cooling_saldos', function (Blueprint $table) {
            $table->dropUnique('pcs_camara_slot_unique');
            $table->string('banda', 50)->default('B1')->nullable(false)->change();
            $table->string('fila', 50)->nullable(false)->change();
            $table->string('columna', 50)->nullable(false)->change();
            $table->string('altura', 50)->nullable(false)->change();
            $table->string('nivel', 50)->nullable(false)->change();
            $table->unique(
                ['camara_id', 'banda', 'fila', 'columna', 'altura', 'nivel'],
                'pcs_camara_slot_unique'
            );
        });
    }
};
