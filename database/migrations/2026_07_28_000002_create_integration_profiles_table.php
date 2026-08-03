<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('integration_clients');
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->string('direccion', 20); // entrada, salida
            $table->string('estado', 30)->default('borrador'); // borrador, en_pruebas, publicado, inactivo, archivado
            $table->string('tipo_salida', 50)->default('excel'); // excel, csv, json, custom
            $table->string('source_adapter', 100)->nullable();
            $table->string('exporter', 100)->nullable();
            $table->string('zona_horaria', 50)->default('UTC');
            $table->json('error_config')->nullable();
            $table->json('idempotency_config')->nullable();
            $table->json('retencion_config')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_profiles');
    }
};
