<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_lines', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('tipo', 20);
            $table->string('especie', 80);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['especie', 'activo'], 'idx_packing_lines_especie_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_lines');
    }
};

