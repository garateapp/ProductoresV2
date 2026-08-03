<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_version_id')->constrained('integration_profile_versions')->cascadeOnDelete();
            $table->string('tipo', 50); // direct, constant, mapping, composite_mapping, multi_output_mapping, concatenation, math, conditional, format, related_field, custom
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->integer('orden')->unsigned()->default(0);
            $table->json('configuracion')->nullable();
            $table->boolean('obligatoria')->default(false);
            $table->string('politica_error', 30)->default('detener'); // stop_record, mark_pending, use_default, skip_field, log_warning
            $table->string('valor_defecto', 500)->nullable();
            $table->string('mensaje_error_personalizado', 500)->nullable();
            $table->boolean('activo')->default(true);

            $table->index(['profile_version_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_rules');
    }
};
