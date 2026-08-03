<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_material_families')) {
            Schema::create('inventory_material_families', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_units')) {
            Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_materials')) {
            Schema::create('inventory_materials', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->foreignId('family_id')->nullable()->constrained('inventory_material_families');
            $table->foreignId('unit_id')->nullable()->constrained('inventory_units');
            $table->string('tipo_material', 50)->default('consumo');
            $table->decimal('sap_on_hand', 18, 4)->default(0);
            $table->decimal('sap_avg_price', 18, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['family_id', 'activo']);
            $table->index(['tipo_material', 'activo']);
            });
        }

        if (! Schema::hasTable('inventory_locations')) {
            Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->string('tipo', 50);
            $table->boolean('permite_stock_negativo')->default(false);
            $table->json('metadata')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tipo', 'activo']);
            });
        }

        if (! Schema::hasTable('inventory_movement_types')) {
            Schema::create('inventory_movement_types', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->boolean('afecta_stock')->default(true);
            $table->boolean('requiere_origen')->default(false);
            $table->boolean('requiere_destino')->default(false);
            $table->boolean('requiere_motivo')->default(false);
            $table->boolean('permite_direcciones_mixtas')->default(false);
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 50)->unique();
            $table->foreignId('movement_type_id')->constrained('inventory_movement_types');
            $table->dateTime('fecha_movimiento');
            $table->foreignId('origin_location_id')->nullable()->constrained('inventory_locations');
            $table->foreignId('destination_location_id')->nullable()->constrained('inventory_locations');
            $table->string('estado', 30)->default('borrador');
            $table->string('referencia_tipo', 50)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('motivo', 150)->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('receipt_hash', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['fecha_movimiento', 'movement_type_id'], 'idx_inventory_movements_date_type');
            $table->index(['estado', 'movement_type_id'], 'idx_inventory_movements_status_type');
            $table->index(['origin_location_id', 'destination_location_id'], 'idx_inventory_movements_locations');
            $table->index(['referencia_tipo', 'referencia_id'], 'idx_inventory_movements_reference');
            });
        }

        if (! Schema::hasTable('inventory_movement_details')) {
            Schema::create('inventory_movement_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->constrained('inventory_movements')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->string('sentido', 20)->default('salida');
            $table->decimal('cantidad', 18, 4);
            $table->decimal('costo_referencial', 18, 4)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'sentido'], 'idx_inventory_movement_details_material_flow');
            });
        }

        if (! Schema::hasTable('inventory_stock_locations')) {
            Schema::create('inventory_stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('inventory_materials')->cascadeOnDelete();
            $table->decimal('stock_actual', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['location_id', 'material_id'], 'ux_inventory_stock_location_material');
            $table->index(['material_id', 'stock_actual'], 'idx_inventory_stock_material_stock');
            });
        }

        if (! Schema::hasTable('inventory_waste_reasons')) {
            Schema::create('inventory_waste_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_packagings')) {
            Schema::create('inventory_packagings', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->string('tipo', 20)->nullable();
            $table->decimal('peso_std', 18, 4)->nullable();
            $table->string('tramo_sag_embalajes', 100)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('altura', 50)->nullable();
            $table->decimal('cantidad_cajas', 18, 4)->nullable();
            $table->decimal('multiplicador', 18, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_technical_sheets')) {
            Schema::create('inventory_technical_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packaging_id')->constrained('inventory_packagings');
            $table->unsignedInteger('version');
            $table->date('fecha_vigencia_desde');
            $table->date('fecha_vigencia_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['packaging_id', 'version'], 'ux_inventory_sheets_packaging_version');
            $table->index(['packaging_id', 'activo', 'fecha_vigencia_desde'], 'idx_inventory_sheets_active_dates');
            });
        }

        if (! Schema::hasTable('inventory_technical_sheet_unit_items')) {
            Schema::create('inventory_technical_sheet_unit_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('technical_sheet_id');
            $table->unsignedBigInteger('material_id');
            $table->decimal('cantidad_estandar', 18, 6);
            $table->timestamps();

            $table->foreign('technical_sheet_id', 'fk_inv_sheet_unit_sheet')
                ->references('id')
                ->on('inventory_technical_sheets')
                ->cascadeOnDelete();
            $table->foreign('material_id', 'fk_inv_sheet_unit_material')
                ->references('id')
                ->on('inventory_materials');
            });
        }

        if (! Schema::hasTable('inventory_technical_sheet_pallet_items')) {
            Schema::create('inventory_technical_sheet_pallet_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('technical_sheet_id');
            $table->unsignedBigInteger('material_id');
            $table->decimal('cantidad_estandar', 18, 6);
            $table->timestamps();

            $table->foreign('technical_sheet_id', 'fk_inv_sheet_pallet_sheet')
                ->references('id')
                ->on('inventory_technical_sheets')
                ->cascadeOnDelete();
            $table->foreign('material_id', 'fk_inv_sheet_pallet_material')
                ->references('id')
                ->on('inventory_materials');
            });
        }

        if (! Schema::hasTable('inventory_productions')) {
            Schema::create('inventory_productions', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('turno', 50);
            $table->string('linea', 50);
            $table->string('especie', 100)->nullable();
            $table->string('variedad', 100)->nullable();
            $table->foreignId('packaging_id')->constrained('inventory_packagings');
            $table->decimal('cantidad_cajas', 18, 4)->default(0);
            $table->decimal('cantidad_pallets', 18, 4)->default(0);
            $table->string('referencia_tipo', 50)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['fecha', 'turno', 'linea'], 'idx_inventory_productions_date_shift_line');
            $table->index(['packaging_id', 'fecha'], 'idx_inventory_productions_packaging_date');
            });
        }

        if (! Schema::hasTable('inventory_sap_conciliations')) {
            Schema::create('inventory_sap_conciliations', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('material_id')->constrained('inventory_materials')->cascadeOnDelete();
            $table->decimal('stock_sap', 18, 4);
            $table->decimal('stock_interno', 18, 4);
            $table->decimal('diferencia', 18, 4);
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['fecha', 'material_id'], 'idx_inventory_sap_conciliations_date_material');
            });
        }

        DB::table('inventory_movement_types')->upsert([
            ['codigo' => 'TRANSFERENCIA', 'nombre' => 'Transferencia interna', 'afecta_stock' => true, 'requiere_origen' => true, 'requiere_destino' => true, 'requiere_motivo' => false, 'permite_direcciones_mixtas' => false, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'CONSUMO', 'nombre' => 'Consumo productivo', 'afecta_stock' => true, 'requiere_origen' => true, 'requiere_destino' => false, 'requiere_motivo' => false, 'permite_direcciones_mixtas' => false, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'PRODUCCION_INTERMEDIA', 'nombre' => 'Producción intermedia', 'afecta_stock' => true, 'requiere_origen' => true, 'requiere_destino' => true, 'requiere_motivo' => false, 'permite_direcciones_mixtas' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'DEVOLUCION', 'nombre' => 'Devolución', 'afecta_stock' => true, 'requiere_origen' => true, 'requiere_destino' => true, 'requiere_motivo' => false, 'permite_direcciones_mixtas' => false, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'MERMA', 'nombre' => 'Merma', 'afecta_stock' => true, 'requiere_origen' => true, 'requiere_destino' => false, 'requiere_motivo' => true, 'permite_direcciones_mixtas' => false, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'AJUSTE_POS', 'nombre' => 'Ajuste positivo', 'afecta_stock' => true, 'requiere_origen' => false, 'requiere_destino' => true, 'requiere_motivo' => true, 'permite_direcciones_mixtas' => false, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'AJUSTE_NEG', 'nombre' => 'Ajuste negativo', 'afecta_stock' => true, 'requiere_origen' => true, 'requiere_destino' => false, 'requiere_motivo' => true, 'permite_direcciones_mixtas' => false, 'created_at' => now(), 'updated_at' => now()],
        ], ['codigo'], ['nombre', 'afecta_stock', 'requiere_origen', 'requiere_destino', 'requiere_motivo', 'permite_direcciones_mixtas', 'updated_at']);

        DB::table('inventory_locations')->upsert([
            ['codigo' => 'BODEGA_CENTRAL', 'nombre' => 'Bodega Central', 'tipo' => 'bodega', 'permite_stock_negativo' => false, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'ARMADO_CAJAS', 'nombre' => 'Armado de Cajas', 'tipo' => 'armado', 'permite_stock_negativo' => false, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'ALTILLO_1', 'nombre' => 'Altillo 1', 'tipo' => 'altillo', 'permite_stock_negativo' => false, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'PRODUCCION_1', 'nombre' => 'Producción Línea 1', 'tipo' => 'produccion', 'permite_stock_negativo' => false, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'MERMA', 'nombre' => 'Merma', 'tipo' => 'merma', 'permite_stock_negativo' => false, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'AJUSTES', 'nombre' => 'Ajustes', 'tipo' => 'ajuste', 'permite_stock_negativo' => true, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ], ['codigo'], ['nombre', 'tipo', 'permite_stock_negativo', 'activo', 'updated_at']);

        DB::table('inventory_waste_reasons')->upsert([
            ['codigo' => 'ROTURA', 'nombre' => 'Rotura / daño físico', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'HUMEDAD', 'nombre' => 'Daño por humedad', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'OTRO', 'nombre' => 'Otro', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ], ['codigo'], ['nombre', 'activo', 'updated_at']);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sap_conciliations');
        Schema::dropIfExists('inventory_productions');
        Schema::dropIfExists('inventory_technical_sheet_pallet_items');
        Schema::dropIfExists('inventory_technical_sheet_unit_items');
        Schema::dropIfExists('inventory_technical_sheets');
        Schema::dropIfExists('inventory_packagings');
        Schema::dropIfExists('inventory_waste_reasons');
        Schema::dropIfExists('inventory_stock_locations');
        Schema::dropIfExists('inventory_movement_details');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_movement_types');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('inventory_materials');
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('inventory_material_families');
    }
};
