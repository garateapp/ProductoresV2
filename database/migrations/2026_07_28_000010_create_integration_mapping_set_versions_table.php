<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_mapping_set_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapping_set_id')->constrained('integration_mapping_sets')->cascadeOnDelete();
            $table->integer('version')->unsigned();
            $table->string('estado', 30)->default('borrador');
            $table->boolean('inmutable')->default(false);
            $table->string('estrategia_fallback', 30)->default('error'); // error, pending, default, keep_original, null, warning
            $table->string('valor_defecto', 500)->nullable();
            $table->integer('prioridad')->unsigned()->default(0);
            $table->boolean('sensible_mayusculas')->default(true);
            $table->string('tratamiento_espacios', 30)->default('trim'); // none, trim, normalize
            $table->json('config_normalizacion')->nullable();
            $table->date('fecha_inicio_vigencia')->nullable();
            $table->date('fecha_fin_vigencia')->nullable();
            $table->text('descripcion')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mapping_set_id', 'version']);
        });

        Schema::table('integration_mapping_sets', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('integration_mapping_set_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_mapping_set_versions');
    }
};
