<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processes', function (Blueprint $table) {
            $table->id();
            $table->string('especie', 80);
            $table->date('fecha');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->string('estado', 32);
            $table->foreignId('creado_por')->nullable()->constrained('users');
            $table->json('included_packing_line_ids')->nullable();
            $table->timestamps();

            $table->index(['especie', 'fecha'], 'idx_processes_especie_fecha');
            $table->index(['estado'], 'idx_processes_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};

