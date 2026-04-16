<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMaterial;
use App\Models\InventoryMaterialFamily;
use App\Models\InventoryStockPosition;
use App\Models\InventoryUnit;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryStockPositionModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('inventory_material_families', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('nombre')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::create('inventory_materials', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->foreignId('family_id')->nullable();
            $table->foreignId('unit_id')->nullable();
            $table->string('tipo_material')->default('consumo');
            $table->decimal('sap_on_hand', 18, 4)->default(0);
            $table->decimal('sap_avg_price', 18, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('scan_code')->nullable();
            $table->string('nombre');
            $table->string('tipo');
            $table->foreignId('parent_id')->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('path_code')->nullable();
            $table->boolean('es_ubicacion_operable')->default(true);
            $table->boolean('requiere_confirmacion_scan')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('permite_stock_negativo')->default(false);
            $table->json('metadata')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_logistic_units', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate_number')->unique();
            $table->foreignId('material_id');
            $table->foreignId('current_location_id')->nullable();
            $table->string('status')->default('active');
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('available_quantity', 18, 4);
            $table->foreignId('unit_id')->nullable();
            $table->string('lot_code', 100)->nullable();
            $table->string('supplier_lot', 100)->nullable();
            $table->string('production_batch', 100)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('last_moved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_stock_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id');
            $table->foreignId('location_id');
            $table->foreignId('logistic_unit_id')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('lot_code', 100)->nullable();
            $table->string('status', 30)->default('available');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_persists_and_resolves_stock_position_relations(): void
    {
        $user = User::create([
            'name' => 'Inventory User',
            'email' => 'inventory-user@example.com',
            'password' => Hash::make('password'),
        ]);

        $family = InventoryMaterialFamily::create([
            'codigo' => 'FAM-SP',
            'nombre' => 'Posiciones',
            'activo' => true,
        ]);

        $unit = InventoryUnit::create([
            'codigo' => 'UN',
            'nombre' => 'Unidad',
        ]);

        $material = InventoryMaterial::create([
            'codigo' => 'MAT-SP',
            'nombre' => 'Material con posicion',
            'descripcion' => 'Prueba',
            'family_id' => $family->id,
            'unit_id' => $unit->id,
            'tipo_material' => 'consumo',
            'sap_on_hand' => 0,
            'activo' => true,
        ]);

        $location = InventoryLocation::create([
            'codigo' => 'LOC-SP',
            'nombre' => 'Ubicacion posicion',
            'tipo' => 'bodega',
            'activo' => true,
        ]);

        $logisticUnit = InventoryLogisticUnit::create([
            'license_plate_number' => 'LPN-SP-001',
            'material_id' => $material->id,
            'current_location_id' => $location->id,
            'status' => 'active',
            'base_quantity' => 7.5,
            'available_quantity' => 7.5,
            'unit_id' => $unit->id,
            'created_by' => $user->id,
            'lot_code' => 'LOT-SP-001',
        ]);

        $position = InventoryStockPosition::create([
            'material_id' => $material->id,
            'location_id' => $location->id,
            'logistic_unit_id' => $logisticUnit->id,
            'quantity' => 7.5,
            'lot_code' => 'LOT-SP-001',
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('inventory_stock_positions', [
            'id' => $position->id,
            'material_id' => $material->id,
            'location_id' => $location->id,
            'logistic_unit_id' => $logisticUnit->id,
            'quantity' => '7.5000',
            'lot_code' => 'LOT-SP-001',
            'status' => 'available',
        ]);

        $position->refresh();

        $this->assertTrue($position->material->is($material));
        $this->assertTrue($position->location->is($location));
        $this->assertTrue($position->logisticUnit->is($logisticUnit));
        $this->assertSame(7.5, (float) $position->quantity);
        $this->assertSame('available', $position->status);
    }
}
