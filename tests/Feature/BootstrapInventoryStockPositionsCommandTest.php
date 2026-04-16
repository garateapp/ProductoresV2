<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMaterial;
use App\Models\InventoryMaterialFamily;
use App\Models\InventoryStockLocation;
use App\Models\InventoryStockPosition;
use App\Models\InventoryUnit;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BootstrapInventoryStockPositionsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
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

        Schema::create('inventory_stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id');
            $table->foreignId('material_id');
            $table->decimal('stock_actual', 18, 4)->default(0);
            $table->unsignedBigInteger('last_ledger_event_id')->nullable();
            $table->dateTime('last_rebuilt_at')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'material_id'], 'ux_inventory_stock_location_material');
        });

        $migration = require base_path('database/migrations/2026_04_16_000000_create_inventory_stock_positions_table.php');
        $migration->up();

        User::create([
            'name' => 'Inventory Admin',
            'email' => 'inventory-admin@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'inventory_stock_positions',
            'inventory_stock_locations',
            'inventory_logistic_units',
            'inventory_locations',
            'inventory_materials',
            'inventory_units',
            'inventory_material_families',
            'users',
        ] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }

        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_it_bootstraps_positions_from_current_stock_and_resolves_lpn_links_idempotently(): void
    {
        Log::spy();

        $family = InventoryMaterialFamily::create([
            'codigo' => 'FAM-BOOT',
            'nombre' => 'Bootstrap',
            'activo' => true,
        ]);

        $unit = InventoryUnit::create([
            'codigo' => 'UN',
            'nombre' => 'Unidad',
        ]);

        $materialA = InventoryMaterial::create([
            'codigo' => 'MAT-BOOT-A',
            'nombre' => 'Material A',
            'descripcion' => null,
            'family_id' => $family->id,
            'unit_id' => $unit->id,
            'tipo_material' => 'consumo',
            'sap_on_hand' => 0,
            'activo' => true,
        ]);

        $materialB = InventoryMaterial::create([
            'codigo' => 'MAT-BOOT-B',
            'nombre' => 'Material B',
            'descripcion' => null,
            'family_id' => $family->id,
            'unit_id' => $unit->id,
            'tipo_material' => 'consumo',
            'sap_on_hand' => 0,
            'activo' => true,
        ]);

        $location = InventoryLocation::create([
            'codigo' => 'LOC-BOOT',
            'scan_code' => 'SCAN-BOOT',
            'nombre' => 'Ubicacion bootstrap',
            'tipo' => 'bodega',
            'activo' => true,
        ]);

        $otherLocation = InventoryLocation::create([
            'codigo' => 'LOC-OTHER',
            'scan_code' => 'SCAN-OTHER',
            'nombre' => 'Otra ubicacion',
            'tipo' => 'bodega',
            'activo' => true,
        ]);

        $user = User::firstOrFail();

        $linkedUnitA = InventoryLogisticUnit::create([
            'license_plate_number' => 'LPN-BOOT-001',
            'material_id' => $materialA->id,
            'current_location_id' => $location->id,
            'status' => 'active',
            'base_quantity' => 8,
            'available_quantity' => 8,
            'unit_id' => $unit->id,
            'lot_code' => 'LOT-A-001',
            'reference_type' => null,
            'reference_id' => null,
            'created_by' => $user->id,
        ]);

        $linkedUnitB = InventoryLogisticUnit::create([
            'license_plate_number' => 'LPN-BOOT-002',
            'material_id' => $materialA->id,
            'current_location_id' => $location->id,
            'status' => 'active',
            'base_quantity' => 4,
            'available_quantity' => 4,
            'unit_id' => $unit->id,
            'lot_code' => null,
            'reference_type' => null,
            'reference_id' => null,
            'created_by' => $user->id,
        ]);

        InventoryLogisticUnit::create([
            'license_plate_number' => 'LPN-BOOT-003',
            'material_id' => $materialA->id,
            'current_location_id' => $otherLocation->id,
            'status' => 'active',
            'base_quantity' => 3,
            'available_quantity' => 3,
            'unit_id' => $unit->id,
            'lot_code' => 'LOT-OTHER',
            'reference_type' => null,
            'reference_id' => null,
            'created_by' => $user->id,
        ]);

        InventoryStockLocation::create([
            'location_id' => $location->id,
            'material_id' => $materialA->id,
            'stock_actual' => 15,
        ]);

        InventoryStockLocation::create([
            'location_id' => $location->id,
            'material_id' => $materialB->id,
            'stock_actual' => 6,
        ]);

        $this->artisan('inventory:bootstrap-stock-positions')
            ->assertExitCode(0);

        $this->assertSame(4, InventoryStockPosition::query()->count());

        $this->assertDatabaseHas('inventory_stock_positions', [
            'material_id' => $materialA->id,
            'location_id' => $location->id,
            'logistic_unit_id' => $linkedUnitA->id,
            'quantity' => '8.0000',
            'lot_code' => 'LOT-A-001',
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('inventory_stock_positions', [
            'material_id' => $materialA->id,
            'location_id' => $location->id,
            'logistic_unit_id' => $linkedUnitB->id,
            'quantity' => '4.0000',
            'lot_code' => null,
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('inventory_stock_positions', [
            'material_id' => $materialA->id,
            'location_id' => $location->id,
            'logistic_unit_id' => null,
            'quantity' => '3.0000',
            'lot_code' => null,
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('inventory_stock_positions', [
            'material_id' => $materialB->id,
            'location_id' => $location->id,
            'logistic_unit_id' => null,
            'quantity' => '6.0000',
            'lot_code' => null,
            'status' => 'available',
        ]);

        Log::shouldHaveReceived('warning')->with(
            'inventory.bootstrap_stock_positions.unresolved_row',
            \Mockery::on(function (array $context): bool {
                return ($context['material_id'] ?? null) !== null
                    && ($context['location_id'] ?? null) !== null
                    && ($context['quantity'] ?? null) === 6.0;
            })
        );

        $this->artisan('inventory:bootstrap-stock-positions')
            ->assertExitCode(0);

        $this->assertSame(4, InventoryStockPosition::query()->count());
    }
}
