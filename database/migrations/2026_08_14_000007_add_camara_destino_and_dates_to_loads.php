<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->foreignId('camara_destino_id')->nullable()->after('tunel_id')->constrained('pre_cooling_camaras')->nullOnDelete();
            $table->decimal('temperatura_objetivo', 5, 2)->nullable()->after('observaciones');
            $table->dateTime('fecha_hora_termino')->nullable()->after('fecha_hora_fin');
            $table->dateTime('fecha_hora_descarga')->nullable()->after('fecha_hora_termino');
        });
    }

    public function down(): void
    {
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->dropForeign(['camara_destino_id']);
            $table->dropColumn(['camara_destino_id', 'temperatura_objetivo', 'fecha_hora_termino', 'fecha_hora_descarga']);
        });
    }
};
