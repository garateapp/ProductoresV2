<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->string('numero', 50)->nullable()->after('id');
            $table->timestamp('fecha_hora_inversion')->nullable()->after('fecha_hora_inicio');
            $table->foreignId('usuario_inversion_id')->nullable()->after('usuario_inicio_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->dropColumn(['numero', 'fecha_hora_inversion']);
            $table->dropForeign(['usuario_inversion_id']);
            $table->dropColumn('usuario_inversion_id');
        });
    }
};
