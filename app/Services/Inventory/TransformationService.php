<?php

namespace App\Services\Inventory;

use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMovementType;
use App\Models\InventoryStockPosition;
use App\Models\InventoryTechnicalSheet;
use App\Models\InventoryWasteReason;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TransformationService
{
    public function __construct(
        protected MovementService $movementService,
        protected LogisticUnitService $logisticUnitService
    ) {
    }

    public function validateAvailability(int $sheetId, float $quantity, int $locationId): array
    {
        $sheet = InventoryTechnicalSheet::query()
            ->with(['unitItems.material', 'unitItems.replacementMaterial', 'palletItems.material', 'palletItems.replacementMaterial'])
            ->findOrFail($sheetId);

        $requirements = $this->calculateRequirements($sheet, $quantity);
        $availability = [];
        $hasErrors = false;

        foreach ($requirements as $materialId => $req) {
            // Check primary stock
            $stock = \App\Models\InventoryStockLocation::query()
                ->where('location_id', $locationId)
                ->where('material_id', $materialId)
                ->first();

            $availableQty = (float) ($stock->stock_actual ?? 0);
            $shortage = max(0, $req['quantity'] - $availableQty);

            $replacementAvailable = 0;
            $replacementMaterial = null;

            // If shortage and has replacement, check replacement stock
            if ($shortage > 0 && $req['replacement_material_id']) {
                $replacementStock = \App\Models\InventoryStockLocation::query()
                    ->where('location_id', $locationId)
                    ->where('material_id', $req['replacement_material_id'])
                    ->first();
                
                $replacementAvailable = (float) ($replacementStock->stock_actual ?? 0);
                $replacementMaterial = \App\Models\InventoryMaterial::find($req['replacement_material_id']);

                // If total (primary + replacement) is enough, we mark as OK but note the use of replacement
                if (($availableQty + $replacementAvailable) >= $req['quantity']) {
                    $shortage = 0;
                }
            }

            if ($shortage > 0) {
                $hasErrors = true;
            }

            $availability[] = [
                'material_id' => $materialId,
                'codigo' => $req['codigo'],
                'nombre' => $req['nombre'],
                'required' => $req['quantity'],
                'available' => $availableQty,
                'shortage' => $shortage,
                'has_replacement' => !!$req['replacement_material_id'],
                'replacement_available' => $replacementAvailable,
                'replacement_name' => $replacementMaterial?->nombre,
                'replacement_code' => $replacementMaterial?->codigo,
            ];
        }

        if ($hasErrors) {
             throw ValidationException::withMessages([
                'availability' => 'Stock insuficiente en la ubicación para cumplir con la ficha técnica (considerando reemplazos).',
                'details' => $availability
            ]);
        }

        return $availability;
    }

    private function calculateRequirements(InventoryTechnicalSheet $sheet, float $quantity): array
    {
        $requirements = [];

        foreach ($sheet->unitItems as $item) {
            $requirements[$item->material_id] = [
                'codigo' => $item->material->codigo,
                'nombre' => $item->material->nombre,
                'replacement_material_id' => $item->replacement_material_id,
                'quantity' => (float) $item->cantidad_estandar * $quantity,
            ];
        }

        return $requirements;
    }

    public function transform(array $data, int $userId): InventoryLogisticUnit
    {
        $sheetId = (int) $data['technical_sheet_id'];
        $quantity = (float) $data['quantity'];
        $locationId = (int) $data['location_id'];

        // Validar disponibilidad antes de procesar
        $this->validateAvailability($sheetId, $quantity, $locationId);

        $sheet = InventoryTechnicalSheet::query()->findOrFail($sheetId);

        if (! $sheet->es_semielaborado || ! $sheet->material_id) {
            throw ValidationException::withMessages([
                'technical_sheet_id' => 'La ficha técnica debe corresponder a un semielaborado con material definido.',
            ]);
        }

        $units = $this->resolveAndValidateInputs($data['inputs']);
        $positionBalances = [];
        $consumptionDetailsByLocation = [];
        $wasteDetailsByGroup = [];

        foreach ($data['inputs'] as $input) {
            $unit = $units[$input['lpn_code']];

            foreach ($this->allocateFromPallet($unit, (float) ($input['consumed'] ?? 0), $positionBalances) as $allocation) {
                $consumptionDetailsByLocation[$allocation['location_id']][] = $this->movementDetail($unit, $allocation);
            }

            foreach (($input['wastes'] ?? []) as $waste) {
                foreach ($this->allocateFromPallet($unit, (float) $waste['quantity'], $positionBalances) as $allocation) {
                    $key = implode('|', [
                        $allocation['location_id'],
                        (int) $waste['waste_reason_id'],
                        (int) $waste['waste_type_id'],
                    ]);

                    $wasteDetailsByGroup[$key]['location_id'] = $allocation['location_id'];
                    $wasteDetailsByGroup[$key]['waste_reason_id'] = (int) $waste['waste_reason_id'];
                    $wasteDetailsByGroup[$key]['waste_type_id'] = (int) $waste['waste_type_id'];
                    $wasteDetailsByGroup[$key]['details'][] = $this->movementDetail($unit, $allocation);
                }
            }
        }

        $newUnit = $this->logisticUnitService->create([
            'license_plate_number' => $this->logisticUnitService->suggestLicensePlateNumber((int) $sheet->material_id),
            'material_id' => (int) $sheet->material_id,
            'current_location_id' => (int) $data['location_id'],
            'status' => 'active',
            'available_quantity' => (float) $data['quantity'],
            'base_quantity' => (float) $data['quantity'],
            'reference_type' => 'semi_finished_production',
            'reference_id' => (int) $sheet->id,
            'metadata' => [
                'workflow' => 'semi_finished_production',
                'technical_sheet_id' => (int) $sheet->id,
            ],
        ], $userId);

        $this->createConsumptionMovements($consumptionDetailsByLocation, $newUnit, $userId);
        $this->createWasteMovements($wasteDetailsByGroup, $newUnit, $userId);

        return $newUnit;
    }

    private function resolveAndValidateInputs(array $inputs): array
    {
        $units = [];

        foreach ($inputs as $input) {
            $code = trim((string) $input['lpn_code']);
            $unit = $this->logisticUnitService->findByCode($code);

            if (! $unit) {
                throw ValidationException::withMessages([
                    'inputs' => "El LPN {$code} no existe.",
                ]);
            }

            $required = (float) ($input['consumed'] ?? 0)
                + collect($input['wastes'] ?? [])->sum(fn (array $waste) => (float) ($waste['quantity'] ?? 0));

            if ($required <= 0) {
                throw ValidationException::withMessages([
                    'inputs' => "El LPN {$unit->license_plate_number} debe tener consumo o merma.",
                ]);
            }

            if ($required > (float) $unit->available_quantity) {
                throw ValidationException::withMessages([
                    'inputs' => "El LPN {$unit->license_plate_number} no tiene stock suficiente.",
                ]);
            }

            $units[$code] = $unit;
        }

        return $units;
    }

    private function allocateFromPallet(InventoryLogisticUnit $unit, float $quantity, array &$positionBalances): array
    {
        if ($quantity <= 0) {
            return [];
        }

        $positions = $this->availablePositions($unit, $positionBalances);
        $remaining = round($quantity, 4);
        $allocations = [];

        foreach ($positions as $position) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $positionBalances[$unit->id][$position->id]['remaining'];
            if ($available <= 0) {
                continue;
            }

            $taken = round(min($remaining, $available), 4);
            $positionBalances[$unit->id][$position->id]['remaining'] = round($available - $taken, 4);
            $remaining = round($remaining - $taken, 4);

            $allocations[] = [
                'position_id' => $position->id,
                'location_id' => $position->location_id,
                'quantity' => $taken,
            ];
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'inputs' => "El LPN {$unit->license_plate_number} no tiene stock suficiente.",
            ]);
        }

        return $allocations;
    }

    private function availablePositions(InventoryLogisticUnit $unit, array &$positionBalances): Collection
    {
        if (! isset($positionBalances[$unit->id])) {
            $positions = InventoryStockPosition::query()
                ->where('logistic_unit_id', $unit->id)
                ->where('quantity', '>', 0)
                ->orderBy('id')
                ->get();

            if ($positions->isEmpty()) {
                throw ValidationException::withMessages([
                    'inputs' => "El LPN {$unit->license_plate_number} no tiene posiciones de stock disponibles.",
                ]);
            }

            $positionBalances[$unit->id] = $positions
                ->mapWithKeys(fn (InventoryStockPosition $position) => [
                    $position->id => ['remaining' => (float) $position->quantity],
                ])
                ->all();

            return $positions;
        }

        return InventoryStockPosition::query()
            ->whereIn('id', array_keys($positionBalances[$unit->id]))
            ->orderBy('id')
            ->get();
    }

    private function movementDetail(InventoryLogisticUnit $unit, array $allocation): array
    {
        return [
            'material_id' => $unit->material_id,
            'position_id' => $allocation['position_id'],
            'cantidad' => $allocation['quantity'],
            'sentido' => 'salida',
            'observacion' => "LPN {$unit->license_plate_number}",
        ];
    }

    private function createConsumptionMovements(array $detailsByLocation, InventoryLogisticUnit $newUnit, int $userId): void
    {
        if (empty($detailsByLocation)) {
            return;
        }

        $type = InventoryMovementType::query()->where('codigo', 'CONSUMO')->firstOrFail();

        foreach ($detailsByLocation as $locationId => $details) {
            $this->movementService->create([
                'movement_type_id' => $type->id,
                'fecha_movimiento' => now(),
                'origin_location_id' => (int) $locationId,
                'motivo' => 'Consumo en producción de semielaborado',
                'referencia_tipo' => 'semi_finished_production',
                'referencia_id' => $newUnit->id,
                'metadata' => [
                    'workflow' => 'semi_finished_production',
                    'output_logistic_unit_id' => $newUnit->id,
                    'output_lpn' => $newUnit->license_plate_number,
                ],
                'details' => $details,
            ], $userId, true);
        }
    }

    private function createWasteMovements(array $groups, InventoryLogisticUnit $newUnit, int $userId): void
    {
        if (empty($groups)) {
            return;
        }

        $type = InventoryMovementType::query()->where('codigo', 'MERMA')->firstOrFail();

        foreach ($groups as $group) {
            $reason = InventoryWasteReason::query()->findOrFail((int) $group['waste_reason_id']);

            $this->movementService->create([
                'movement_type_id' => $type->id,
                'fecha_movimiento' => now(),
                'origin_location_id' => (int) $group['location_id'],
                'motivo' => $reason->nombre,
                'waste_reason_id' => $reason->id,
                'referencia_tipo' => 'semi_finished_production',
                'referencia_id' => $newUnit->id,
                'metadata' => [
                    'workflow' => 'semi_finished_production',
                    'detected_location_id' => (int) $group['location_id'],
                    'waste_type_id' => (int) $group['waste_type_id'],
                    'output_logistic_unit_id' => $newUnit->id,
                    'output_lpn' => $newUnit->license_plate_number,
                ],
                'details' => $group['details'],
            ], $userId, true);
        }
    }
}
