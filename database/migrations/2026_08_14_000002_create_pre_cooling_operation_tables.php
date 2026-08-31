<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_cooling_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_proceso_id')->constrained('pre_cooling_tipos_procesos')->restrictOnDelete();
            $table->foreignId('tunel_id')->constrained('pre_cooling_tuneles')->restrictOnDelete();
            $table->string('banda', 50);
            $table->string('posicion', 50);
            $table->string('altura', 50);
            $table->string('estado', 20)->default('ingresado');
            $table->dateTime('fecha_hora_inicio')->nullable();
            $table->foreignId('usuario_ingreso_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('usuario_inicio_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['tunel_id', 'banda', 'posicion', 'altura']);
            $table->index(['tunel_id', 'estado']);
        });

        Schema::create('pre_cooling_load_folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_id')->constrained('pre_cooling_loads')->cascadeOnDelete();
            $table->foreignId('tipo_proceso_id')->constrained('pre_cooling_tipos_procesos')->restrictOnDelete();
            $table->string('folio', 50);
            $table->string('nivel', 50);
            $table->string('exportadora', 255)->nullable();
            $table->string('productor', 255)->nullable();
            $table->string('especie', 255)->nullable();
            $table->string('variedad', 255)->nullable();
            $table->string('embalaje', 255)->nullable();
            $table->string('categoria', 100)->nullable();
            $table->string('calibre', 100)->nullable();
            $table->unsignedInteger('cajas')->nullable();
            $table->unsignedInteger('pallets')->nullable();
            $table->decimal('temperatura_inicial', 5, 2)->nullable();
            $table->json('atributos')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['load_id', 'folio']);
            $table->unique(['load_id', 'nivel']);
            $table->unique(['tipo_proceso_id', 'folio']);
            $table->index('folio');
        });

        Schema::create('pre_cooling_saldos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camara_id')->constrained('pre_cooling_camaras')->restrictOnDelete();
            $table->string('fila', 50);
            $table->string('columna', 50);
            $table->string('altura', 50);
            $table->string('nivel', 50);
            $table->string('folio', 50);
            $table->foreignId('tipo_proceso_id')->nullable()->constrained('pre_cooling_tipos_procesos')->restrictOnDelete();
            $table->unsignedInteger('cajas')->nullable();
            $table->unsignedInteger('pallets')->nullable();
            $table->string('especie', 255)->nullable();
            $table->string('variedad', 255)->nullable();
            $table->string('productor', 255)->nullable();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['camara_id', 'fila', 'columna', 'altura', 'nivel']);
            $table->index('folio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_cooling_saldos');
        Schema::dropIfExists('pre_cooling_load_folios');
        Schema::dropIfExists('pre_cooling_loads');
    }
};
