<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_auto_consumption_folios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_g_produccion');
            $table->string('folio', 50);
            $table->string('numero_g_produccion', 20)->nullable();
            $table->string('c_embalaje', 50)->nullable();
            $table->string('n_embalaje', 200)->nullable();
            $table->decimal('cantidad', 18, 4)->default(0);
            $table->decimal('peso_neto', 18, 4)->nullable();
            $table->string('n_linea_proceso', 100)->nullable();
            $table->string('n_turno', 50)->nullable();
            $table->string('n_calibre', 50)->nullable();
            $table->string('n_especie', 100)->nullable();
            $table->string('n_variedad', 100)->nullable();
            $table->date('fecha_produccion')->nullable();
            $table->foreignId('packaging_id')->nullable()->constrained('inventory_packagings');
            $table->foreignId('production_id')->nullable()->constrained('inventory_productions');
            $table->foreignId('movement_id')->nullable()->constrained('inventory_movements');
            $table->foreignId('origin_location_id')->nullable()->constrained('inventory_locations');
            $table->string('estado', 30);
            $table->text('detalle_estado')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['id_g_produccion', 'folio'], 'ux_auto_consumption_folio');
            $table->index(['estado'], 'idx_auto_consumption_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_auto_consumption_folios');
    }
};