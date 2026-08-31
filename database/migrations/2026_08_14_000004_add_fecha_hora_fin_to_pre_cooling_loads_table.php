<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->dateTime('fecha_hora_fin')->nullable()->after('fecha_hora_inicio');
            $table->foreignId('usuario_fin_id')->nullable()->after('usuario_inicio_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usuario_fin_id');
            $table->dropColumn('fecha_hora_fin');
        });
    }
};
