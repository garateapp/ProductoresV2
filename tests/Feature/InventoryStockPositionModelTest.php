<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMaterial;
use App\Models\InventoryMaterialFamily;
use App\Models\InventoryStockPosition;
use App\Models\InventoryUnit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;
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

        $migration = require base_path('database/migrations/2026_04_16_000000_create_inventory_stock_positions_table.php');
        $migration->up();
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'inventory_stock_positions',
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

        $logisticUnitWithoutLot = InventoryLogisticUnit::create([
            'license_plate_number' => 'LPN-SP-002',
            'material_id' => $material->id,
            'current_location_id' => $location->id,
            'status' => 'active',
            'base_quantity' => 4.0,
            'available_quantity' => 4.0,
            'unit_id' => $unit->id,
            'created_by' => $user->id,
            'lot_code' => 'LOT-SP-002',
        ]);

        $position = InventoryStockPosition::create([
            'material_id' => $material->id,
            'location_id' => $location->id,
            'logistic_unit_id' => $logisticUnit->id,
            'quantity' => 7.5,
            'lot_code' => 'LOT-SP-001',
            'status' => 'available',
        ]);

        $positionWithDifferentStatus = InventoryStockPosition::create([
            'material_id' => $material->id,
            'location_id' => $location->id,
            'logistic_unit_id' => $logisticUnit->id,
            'quantity' => 2.0,
            'lot_code' => 'LOT-SP-001',
            'status' => 'reserved',
        ]);

        $positionWithDifferentLot = InventoryStockPosition::create([
            'material_id' => $material->id,
            'location_id' => $location->id,
            'logistic_unit_id' => $logisticUnit->id,
            'quantity' => 1.5,
            'lot_code' => 'LOT-SP-002',
            'status' => 'available',
        ]);

        $nullScopedPosition = InventoryStockPosition::create([
            'material_id' => $material->id,
            'location_id' => $location->id,
            'logistic_unit_id' => null,
            'quantity' => 4.0,
            'lot_code' => null,
            'status' => 'available',
        ]);

        $emptyLotNormalizedPosition = InventoryStockPosition::create([
            'material_id' => $material->id,
            'location_id' => $location->id,
            'logistic_unit_id' => $logisticUnitWithoutLot->id,
            'quantity' => 2.25,
            'lot_code' => '',
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

        $this->assertDatabaseHas('inventory_stock_positions', [
            'id' => $positionWithDifferentStatus->id,
            'material_id' => $material->id,
            'location_id' => $location->id,
            'logistic_unit_id' => $logisticUnit->id,
            'quantity' => '2.0000',
            'lot_code' => 'LOT-SP-001',
            'status' => 'reserved',
        ]);

        $this->assertDatabaseHas('inventory_stock_positions', [
            'id' => $positionWithDifferentLot->id,
            'material_id' => $material->id,
            'location_id' => $location->id,
            'logistic_unit_id' => $logisticUnit->id,
            'quantity' => '1.5000',
            'lot_code' => 'LOT-SP-002',
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('inventory_stock_positions', [
            'id' => $nullScopedPosition->id,
            'material_id' => $material->id,
            'location_id' => $location->id,
            'logistic_unit_id' => null,
            'quantity' => '4.0000',
            'lot_code' => null,
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('inventory_stock_positions', [
            'id' => $emptyLotNormalizedPosition->id,
            'material_id' => $material->id,
            'location_id' => $location->id,
            'logistic_unit_id' => $logisticUnitWithoutLot->id,
            'quantity' => '2.2500',
            'lot_code' => null,
            'status' => 'available',
        ]);

        $this->assertStockPositionDuplicateFails(
            fn () => InventoryStockPosition::create([
                'material_id' => $material->id,
                'location_id' => $location->id,
                'logistic_unit_id' => $logisticUnit->id,
                'quantity' => 7.5,
                'lot_code' => 'LOT-SP-001',
                'status' => 'available',
            ])
        );

        $this->assertStockPositionDeleteBlocked($logisticUnit, fn () => $logisticUnit->delete());

        $this->assertStockPositionDuplicateFails(
            fn () => InventoryStockPosition::create([
                'material_id' => $material->id,
                'location_id' => $location->id,
                'logistic_unit_id' => null,
                'quantity' => 4.0,
                'lot_code' => null,
                'status' => 'available',
            ])
        );

        $position->refresh();
        $positionWithDifferentStatus->refresh();
        $positionWithDifferentLot->refresh();
        $nullScopedPosition->refresh();
        $emptyLotNormalizedPosition->refresh();

        $this->assertTrue($position->material->is($material));
        $this->assertTrue($position->location->is($location));
        $this->assertTrue($position->logisticUnit->is($logisticUnit));
        $this->assertSame(7.5, (float) $position->quantity);
        $this->assertSame('available', $position->status);
        $this->assertArrayNotHasKey('logistic_unit_key', $position->toArray());
        $this->assertArrayNotHasKey('lot_code_key', $position->toArray());

        $this->assertTrue($material->stockPositions()->whereKey($position->id)->exists());
        $this->assertTrue($material->stockPositions()->whereKey($positionWithDifferentStatus->id)->exists());
        $this->assertTrue($material->stockPositions()->whereKey($positionWithDifferentLot->id)->exists());
        $this->assertTrue($material->stockPositions()->whereKey($nullScopedPosition->id)->exists());
        $this->assertTrue($material->stockPositions()->whereKey($emptyLotNormalizedPosition->id)->exists());
        $this->assertTrue($location->stockPositions()->whereKey($position->id)->exists());
        $this->assertTrue($location->stockPositions()->whereKey($positionWithDifferentStatus->id)->exists());
        $this->assertTrue($location->stockPositions()->whereKey($positionWithDifferentLot->id)->exists());
        $this->assertTrue($location->stockPositions()->whereKey($nullScopedPosition->id)->exists());
        $this->assertTrue($location->stockPositions()->whereKey($emptyLotNormalizedPosition->id)->exists());
        $this->assertTrue($logisticUnit->stockPositions()->whereKey($position->id)->exists());
        $this->assertTrue($logisticUnit->stockPositions()->whereKey($positionWithDifferentStatus->id)->exists());
        $this->assertTrue($logisticUnit->stockPositions()->whereKey($positionWithDifferentLot->id)->exists());
        $this->assertFalse($logisticUnit->stockPositions()->whereKey($nullScopedPosition->id)->exists());
        $this->assertFalse($logisticUnit->stockPositions()->whereKey($emptyLotNormalizedPosition->id)->exists());
        $this->assertCount(5, $material->stockPositions);
        $this->assertCount(5, $location->stockPositions);
        $this->assertCount(3, $logisticUnit->stockPositions);
    }

    private function assertStockPositionDuplicateFails(callable $createAttempt): void
    {
        $beforeCount = InventoryStockPosition::query()->count();

        try {
            $createAttempt();
            $this->fail('Expected duplicate stock position insert to fail.');
        } catch (QueryException $e) {
            $message = strtolower($e->getMessage());

            $this->assertTrue(
                str_contains((string) $e->getCode(), '23000')
                    || str_contains($message, 'unique')
                    || str_contains($message, 'duplicate')
                    || str_contains($message, 'constraint'),
                'Expected a unique-constraint violation, got: '.$e->getMessage()
            );

            $this->assertSame($beforeCount, InventoryStockPosition::query()->count());
        } catch (Throwable $e) {
            $this->fail('Expected a unique-constraint violation, got '.get_class($e).': '.$e->getMessage());
        }
    }

    private function assertStockPositionDeleteBlocked(InventoryLogisticUnit $logisticUnit, callable $deleteAttempt): void
    {
        try {
            $deleteAttempt();
            $this->fail('Expected referenced logistic unit delete to be blocked.');
        } catch (QueryException $e) {
            $message = strtolower($e->getMessage());

            $this->assertTrue(
                str_contains((string) $e->getCode(), '23000')
                    || str_contains($message, 'foreign key')
                    || str_contains($message, 'constraint')
                || str_contains($message, 'restrict'),
                'Expected a restrictive foreign-key violation, got: '.$e->getMessage()
            );

            $this->assertTrue($logisticUnit->exists);
            $this->assertDatabaseHas('inventory_logistic_units', [
                'id' => $logisticUnit->id,
            ]);
        } catch (Throwable $e) {
            $this->fail('Expected referenced logistic unit delete to be blocked, got '.get_class($e).': '.$e->getMessage());
        }
    }
}
