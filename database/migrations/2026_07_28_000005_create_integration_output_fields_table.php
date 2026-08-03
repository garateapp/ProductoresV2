<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_output_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_version_id')->constrained('integration_profile_versions')->cascadeOnDelete();
            $table->string('clave_externa', 100);
            $table->string('etiqueta', 200);
            $table->text('descripcion')->nullable();
            $table->string('tipo_dato', 30); // string, integer, decimal, boolean, date, datetime, json, array
            $table->boolean('obligatorio')->default(false);
            $table->boolean('permite_nulo')->default(true);
            $table->string('valor_defecto', 500)->nullable();
            $table->integer('largo_maximo')->unsigned()->nullable();
            $table->integer('precision')->unsigned()->nullable();
            $table->integer('escala_decimal')->unsigned()->nullable();
            $table->string('mascara_formato', 100)->nullable();
            $table->integer('posicion')->unsigned()->default(0);
            $table->boolean('activo')->default(true);

            $table->unique(['profile_version_id', 'clave_externa'], 'int_output_fields_pv_ce_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_output_fields');
    }
};
