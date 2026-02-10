<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            $table->date('fecha_recepcion')->nullable()->after('n_productor');
            $table->string('tipo_proceso', 120)->nullable()->after('fecha_recepcion'); // descripcion_tipo (SQLSRV)
            $table->string('variedad_original', 120)->nullable()->after('tipo_proceso');
            $table->string('productor_real', 180)->nullable()->after('variedad_original');
            $table->string('categoria_origen', 120)->nullable()->after('productor_real');
            $table->string('sdp_centrocosto', 60)->nullable()->after('categoria_origen');
            $table->string('envase_origen', 160)->nullable()->after('sdp_centrocosto');
            $table->string('altura_origen', 60)->nullable()->after('envase_origen');
        });
    }

    public function down(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_recepcion',
                'tipo_proceso',
                'variedad_original',
                'productor_real',
                'categoria_origen',
                'sdp_centrocosto',
                'envase_origen',
                'altura_origen',
            ]);
        });
    }
};

