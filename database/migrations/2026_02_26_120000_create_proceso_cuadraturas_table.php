<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proceso_cuadraturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos')->cascadeOnDelete();
            $table->string('estado', 40)->default('pendiente_cuadratura');
            $table->unsignedInteger('ciclo')->default(1);
            $table->timestamp('enviado_jefe_at')->nullable();
            $table->timestamp('aprobado_jefe_at')->nullable();
            $table->timestamp('rechazado_jefe_at')->nullable();
            $table->text('comentario_rechazo')->nullable();
            $table->unsignedBigInteger('ultimo_actor_id')->nullable();
            $table->string('ultimo_actor_nombre')->nullable();
            $table->string('ultimo_actor_email')->nullable();
            $table->timestamps();

            $table->unique('proceso_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proceso_cuadraturas');
    }
};

