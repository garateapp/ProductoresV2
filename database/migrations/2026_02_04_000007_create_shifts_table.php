<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 80);
            $table->unsignedTinyInteger('horas');
            $table->time('hora_inicio')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo'], 'idx_shifts_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};

