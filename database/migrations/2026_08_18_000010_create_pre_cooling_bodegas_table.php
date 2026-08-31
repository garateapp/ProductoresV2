<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_cooling_bodegas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 255);
            $table->unsignedInteger('filas')->default(0);
            $table->unsignedInteger('columnas')->default(0);
            $table->unsignedInteger('alto_maximo')->default(0);
            $table->unsignedInteger('capacidad')->default(0);
            $table->string('tipo_posiciones', 50)->nullable();
            $table->decimal('pos_x', 8, 2)->default(0);
            $table->decimal('pos_y', 8, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('codigo');
            $table->index('tipo_posiciones');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_cooling_bodegas');
    }
};
