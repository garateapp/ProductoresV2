<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_cooling_tipos_procesos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 255);
            $table->unsignedInteger('tiempo_objetivo_minutos')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('pre_cooling_tuneles', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 255);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('pre_cooling_tunel_parametros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tunel_id')->constrained('pre_cooling_tuneles')->cascadeOnDelete();
            $table->string('dimension', 20);
            $table->string('valor', 50);
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tunel_id', 'dimension', 'valor']);
            $table->index(['tunel_id', 'dimension']);
        });

        Schema::create('pre_cooling_camaras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 255);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('pre_cooling_camara_parametros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camara_id')->constrained('pre_cooling_camaras')->cascadeOnDelete();
            $table->string('dimension', 20);
            $table->string('valor', 50);
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['camara_id', 'dimension', 'valor']);
            $table->index(['camara_id', 'dimension']);
        });

        Schema::create('pre_cooling_atributos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 255);
            $table->string('tipo_dato', 20);
            $table->json('opciones')->nullable();
            $table->boolean('requerido')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_cooling_atributos');
        Schema::dropIfExists('pre_cooling_camara_parametros');
        Schema::dropIfExists('pre_cooling_camaras');
        Schema::dropIfExists('pre_cooling_tunel_parametros');
        Schema::dropIfExists('pre_cooling_tuneles');
        Schema::dropIfExists('pre_cooling_tipos_procesos');
    }
};
