<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospecto_productor', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social')->nullable();
            $table->string('rut')->nullable();
            $table->string('tipo_empresa')->nullable();
            $table->string('direccion_comercial')->nullable();
            $table->string('comuna_comercial')->nullable();
            $table->string('fono')->nullable();
            $table->string('fax_comercial')->nullable();
            $table->string('direccion_predio')->nullable();
            $table->string('comuna_predio')->nullable();
            $table->string('email')->nullable();
            $table->string('fax_contacto')->nullable();
            $table->string('nombre_rep_legal')->nullable();
            $table->string('rut_rep_legal')->nullable();
            $table->string('direccion_rep_legal')->nullable();
            $table->string('banco')->nullable();
            $table->string('nombre_titular')->nullable();
            $table->string('cuenta_corriente')->nullable();
            $table->string('moneda')->nullable();
            $table->string('sucursal')->nullable();
            $table->date('constitucion_fecha_escritura')->nullable();
            $table->string('notario_publico')->nullable();
            $table->json('predios')->nullable();
            $table->json('produccion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospecto_productor');
    }
};
