<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detenciones_maquinas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 255);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('detenciones_motivo_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 255);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('detenciones_motivo_causas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motivo_tipo_id')->constrained('detenciones_motivo_tipos')->restrictOnDelete();
            $table->string('codigo', 50);
            $table->string('nombre', 255);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['motivo_tipo_id', 'codigo']);
            $table->index(['motivo_tipo_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detenciones_motivo_causas');
        Schema::dropIfExists('detenciones_motivo_tipos');
        Schema::dropIfExists('detenciones_maquinas');
    }
};