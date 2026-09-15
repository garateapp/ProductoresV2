<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detenciones_turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')->constrained('detenciones_maquinas')->restrictOnDelete();
            $table->date('fecha');
            $table->dateTime('hora_inicio_turno');
            $table->dateTime('hora_fin_turno')->nullable();
            $table->string('operador', 255)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_created_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['maquina_id', 'fecha']);
            $table->index('fecha');
        });

        Schema::create('detenciones_registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('detenciones_turnos')->cascadeOnDelete();
            $table->foreignId('motivo_causa_id')->constrained('detenciones_motivo_causas')->restrictOnDelete();
            $table->dateTime('hora_detencion');
            $table->dateTime('hora_reinicio')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_created_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['turno_id', 'hora_detencion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detenciones_registros');
        Schema::dropIfExists('detenciones_turnos');
    }
};