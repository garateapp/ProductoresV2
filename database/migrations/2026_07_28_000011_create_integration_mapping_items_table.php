<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_mapping_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapping_set_version_id')->constrained('integration_mapping_set_versions')->cascadeOnDelete();
            $table->string('valor_salida', 500);
            $table->date('fecha_inicio_vigencia')->nullable();
            $table->date('fecha_fin_vigencia')->nullable();
            $table->integer('prioridad')->unsigned()->default(0);
            $table->boolean('activo')->default(true);
            $table->text('observacion')->nullable();
            $table->string('origen', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mapping_set_version_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_mapping_items');
    }
};
