<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_input_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_version_id')->constrained('integration_profile_versions')->cascadeOnDelete();
            $table->string('clave', 100);
            $table->string('etiqueta', 200);
            $table->text('descripcion')->nullable();
            $table->string('tipo_dato', 30); // string, integer, decimal, boolean, date, datetime, json, array
            $table->string('ruta_valor', 200)->nullable();
            $table->boolean('obligatorio')->default(false);
            $table->boolean('permite_nulo')->default(true);
            $table->string('valor_ejemplo', 500)->nullable();
            $table->integer('posicion')->unsigned()->default(0);
            $table->boolean('activo')->default(true);
            $table->json('config_adicional')->nullable();

            $table->unique(['profile_version_id', 'clave'], 'int_input_fields_pv_clave_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_input_fields');
    }
};
