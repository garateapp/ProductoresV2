<?php

namespace App\Services\Inventory;

use App\Models\InventoryConsumptionOrigin;
use App\Models\InventoryLocation;
use App\Models\InventoryManualConsumption;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use App\Models\InventoryStockLocation;
use App\Models\InventoryStockPosition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ManualConsumptionService
{
    public function __construct(
        private readonly MovementService $movementService,
        private readonly SalidasFolioProvider $folioProvider,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId, bool $apply = true): InventoryManualConsumption
    {
        $tipo = (string) $data['tipo_accion'];
        $this->validateAction($tipo, $data);

        $materials = $this->normalizeMaterials($data['materials'] ?? []);
        $semielaboradoMaterialId = (int) ($data['semielaborado_material_id'] ?? 0);
        $fecha = (string) $data['fecha'];
        $location = $this->resolveLocation($data);

        $sourceFolios = [];
        $detalle = null;

        try {
            $sourceFolios = $this->resolveSourceFolios($tipo, $data);
        } catch (\Throwable $e) {
            $detalle = $e->getMessage();
        }

        $attributes = [
            'movement_type_id' => $this->consumeTypeId(),
            'fecha_movimiento' => $fecha,
            'origin_location_id' => $location?->id,
            'motivo' => $this->motivo($tipo),
            'referencia_tipo' => 'manual_consumption',
            'observacion' => $this->observacion($tipo, $sourceFolios),
            'metadata' => [
                'workflow' => 'manual_consumption',
                'tipo_accion' => $tipo,
                'folios' => $sourceFolios,
            ],
        ];

        $movement = null;
        $estado = 'borrador';

        if ($detalle === null) {
            if (! $location) {
                $detalle = 'No se encontró ubicación de origen para la línea/turno indicado.';
            } else {
                try {
                    $details = $this->buildDetails($materials, $location);

                    if ($apply) {
                        $movement = $this->movementService->create([...$attributes, 'details' => $details], $userId, true);
                        $estado = 'aplicado';
                    } else {
                        $movement = $this->movementService->create([...$attributes, 'details' => $details], $userId, false);
                        $estado = 'borrador';
                    }
                } catch (\Throwable $e) {
                    $detalle = $e->getMessage();
                }
            }
        }

        $primary = $materials[0] ?? ['material_id' => 0, 'cantidad' => 0];

        $consumption = InventoryManualConsumption::create([
            'tipo_accion' => $tipo,
            'material_id' => (int) $primary['material_id'],
            'id_g_produccion' => $sourceFolios !== [] ? (int) $sourceFolios[0]['id_g_produccion'] : null,
            'semielaborado_material_id' => $semielaboradoMaterialId > 0 ? $semielaboradoMaterialId : null,
            'cantidad' => (float) $primary['cantidad'],
            'fecha' => $fecha,
            'location_id' => $location?->id,
            'movement_id' => $movement?->id,
            'folio_nuevo' => in_array($tipo, [InventoryManualConsumption::TIPO_REEMBALAJE, InventoryManualConsumption::TIPO_REPROCESO], true)
                ? trim((string) ($data['folio_nuevo'] ?? '')) ?: null
                : null,
            'folios' => $sourceFolios !== [] ? $sourceFolios : null,
            'estado' => $estado,
            'detalle_estado' => $detalle,
            'observacion' => trim((string) ($data['observacion'] ?? '')) ?: null,
            'created_by' => $userId,
        ]);

        $consumption->details()->createMany(
            collect($materials)->map(fn (array $m) => [
                'material_id' => (int) $m['material_id'],
                'cantidad' => (float) $m['cantidad'],
            ])->all()
        );

        return $consumption;
    }

    public function retry(InventoryManualConsumption $consumption, int $userId): InventoryManualConsumption
    {
        if ($consumption->estado === 'aplicado') {
            return $consumption;
        }

        $consumption->estado = 'borrador';
        $consumption->detalle_estado = null;
        $consumption->save();

        $data = [
            'tipo_accion' => $consumption->tipo_accion,
            'materials' => $consumption->details
                ->map(fn ($d) => ['material_id' => (int) $d->material_id, 'cantidad' => (float) $d->cantidad])
                ->all(),
            'semielaborado_material_id' => $consumption->semielaborado_material_id,
            'fecha' => $consumption->fecha?->toDateString(),
            'location_id' => $consumption->location_id,
            'folio_nuevo' => $consumption->folio_nuevo,
            'id_g_produccion' => $consumption->id_g_produccion,
            'folios' => $consumption->folios,
        ];

        $result = $this->create($data, $userId, true);

        $consumption->forceFill([
            'movement_id' => $result->movement_id,
            'location_id' => $result->location_id,
            'estado' => $result->estado,
            'detalle_estado' => $result->detalle_estado,
        ])->save();

        return $consumption->fresh();
    }

    private function validateAction(string $tipo, array $data): void
    {
        if (! in_array($tipo, [
            InventoryManualConsumption::TIPO_REEMBALAJE,
            InventoryManualConsumption::TIPO_REPROCESO,
            InventoryManualConsumption::TIPO_COMPLETAR_SALDOS,
        ], true)) {
            throw ValidationException::withMessages([
                'tipo_accion' => 'Tipo de acción no válido.',
            ]);
        }

        $materials = $this->normalizeMaterials($data['materials'] ?? []);
        if ($materials === []) {
            throw ValidationException::withMessages([
                'materials' => 'Debes indicar al menos un material a consumir.',
            ]);
        }

        if ($tipo === InventoryManualConsumption::TIPO_COMPLETAR_SALDOS) {
            if ($this->extractIds($data['folios'] ?? []) === []) {
                throw ValidationException::withMessages([
                    'folios' => 'Debes indicar al menos un folio de origen que completará el saldo.',
                ]);
            }
        } elseif ((int) ($data['id_g_produccion'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'id_g_produccion' => 'Debes seleccionar el folio de origen de la producción.',
            ]);
        }
    }

    private function resolveLocation(array $data): ?InventoryLocation
    {
        $locationId = (int) ($data['location_id'] ?? 0);
        if ($locationId > 0) {
            return InventoryLocation::query()->where('id', $locationId)->where('activo', true)->first();
        }

        $linea = trim((string) ($data['linea'] ?? ''));
        $turno = trim((string) ($data['turno'] ?? ''));

        $mapping = InventoryConsumptionOrigin::query()
            ->where('linea', $linea)
            ->where('turno', $turno === '' ? '' : $turno)
            ->where('activo', true)
            ->with('location')
            ->first();

        if ($mapping && $mapping->location) {
            return $mapping->location;
        }

        return InventoryLocation::query()
            ->where('tipo', 'produccion')
            ->where('activo', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * Resuelve los folios de origen desde la vista TERMO (V_PKG_Produccion_Salidas).
     * Cada folio de origen debe existir en esa vista.
     *
     * @return list<array{id_g_produccion: int, folio: string}>
     */
    private function resolveSourceFolios(string $tipo, array $data): array
    {
        $ids = $tipo === InventoryManualConsumption::TIPO_COMPLETAR_SALDOS
            ? $this->extractIds($data['folios'] ?? [])
            : [(int) ($data['id_g_produccion'] ?? 0)];

        $ids = array_values(array_filter($ids, fn (int $id) => $id > 0));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'folios' => 'Debes indicar el folio de origen de la producción.',
            ]);
        }

        $found = $this->folioProvider->byIds($ids);

        if ($found->count() !== count($ids)) {
            $missing = array_diff($ids, $found->pluck('id_g_produccion')->map(fn ($id) => (int) $id)->all());
            $labels = collect($missing)->map(fn (int $id) => "#{$id}")->implode(', ');
            throw ValidationException::withMessages([
                'folios' => "Uno o más folios de origen no existen en la vista de producción (V_PKG_Produccion_Salidas): {$labels}.",
            ]);
        }

        return $found
            ->map(fn ($row) => [
                'id_g_produccion' => (int) $row->id_g_produccion,
                'folio' => (string) $row->folio,
            ])
            ->values()
            ->all();
    }

    /**
     * Extrae ids de producción desde un array de ids o de objetos {id_g_produccion}.
     *
     * @return list<int>
     */
    private function extractIds(array $entries): array
    {
        return collect($entries)
            ->map(fn ($entry) => is_array($entry) ? (int) ($entry['id_g_produccion'] ?? 0) : (int) $entry)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    /**
     * Normaliza y consolida los materiales a consumir.
     *
     * @param  array<int, mixed>  $entries
     * @return list<array{material_id: int, cantidad: float}>
     */
    private function normalizeMaterials(array $entries): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            $materialId = (int) ($entry['material_id'] ?? 0);
            $cantidad = (float) ($entry['cantidad'] ?? 0);

            if ($materialId <= 0 || $cantidad <= 0) {
                throw ValidationException::withMessages([
                    'materials' => 'Cada material debe tener un material válido y una cantidad mayor a cero.',
                ]);
            }

            $grouped[$materialId] = ($grouped[$materialId] ?? 0) + $cantidad;
        }

        return collect($grouped)
            ->map(fn (float $cantidad, int $materialId) => [
                'material_id' => $materialId,
                'cantidad' => round($cantidad, 4),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{material_id: int, cantidad: float}>  $materials
     * @return list<array<string, mixed>>
     */
    private function buildDetails(array $materials, InventoryLocation $location): array
    {
        $details = [];

        foreach ($materials as $material) {
            $materialId = (int) $material['material_id'];
            $quantity = (float) $material['cantidad'];

            foreach ($this->buildDetailForMaterial($materialId, $quantity, $location) as $detail) {
                $details[] = $detail;
            }
        }

        return $details;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDetailForMaterial(int $materialId, float $quantity, InventoryLocation $location): array
    {
        $hasPositionsTable = Schema::hasTable('inventory_stock_positions');

        if ($hasPositionsTable) {
            $positions = InventoryStockPosition::query()
                ->where('location_id', $location->id)
                ->where('material_id', $materialId)
                ->where('quantity', '>', 0)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($positions->isNotEmpty()) {
                $details = [];
                $remaining = $quantity;

                foreach ($positions as $position) {
                    if ($remaining <= 0.0001) {
                        break;
                    }

                    $take = round(min($remaining, (float) $position->quantity), 4);
                    $details[] = [
                        'material_id' => $materialId,
                        'cantidad' => $take,
                        'sentido' => 'salida',
                        'position_id' => $position->id,
                    ];
                    $remaining = round($remaining - $take, 4);
                }

                if ($remaining > 0.0001) {
                    throw ValidationException::withMessages([
                        'cantidad' => "Stock insuficiente en posiciones para el material. Faltan {$remaining} unidades.",
                    ]);
                }

                return $details;
            }
        }

        $stock = InventoryStockLocation::query()
            ->where('location_id', $location->id)
            ->where('material_id', $materialId)
            ->lockForUpdate()
            ->first();
        $available = (float) ($stock?->stock_actual ?? 0);

        if (! $location->permite_stock_negativo && $available + 0.0001 < $quantity) {
            throw ValidationException::withMessages([
                'cantidad' => "Stock insuficiente para el material. Disponible: {$available}. Requerido: {$quantity}.",
            ]);
        }

        return [[
            'material_id' => $materialId,
            'cantidad' => $quantity,
            'sentido' => 'salida',
        ]];
    }

    private function consumeTypeId(): int
    {
        return (int) InventoryMovementType::query()->where('codigo', 'CONSUMO')->firstOrFail()->id;
    }

    private function motivo(string $tipo): string
    {
        return match ($tipo) {
            InventoryManualConsumption::TIPO_REEMBALAJE => 'Consumo manual por reembalaje',
            InventoryManualConsumption::TIPO_REPROCESO => 'Consumo manual por reproceso',
            InventoryManualConsumption::TIPO_COMPLETAR_SALDOS => 'Consumo manual para completar saldos',
            default => 'Consumo manual',
        };
    }

    /**
     * @param  list<array{id_g_produccion: int, folio: string}>  $folios
     */
    private function observacion(string $tipo, array $folios): string
    {
        $base = match ($tipo) {
            InventoryManualConsumption::TIPO_REEMBALAJE => 'Reembalaje',
            InventoryManualConsumption::TIPO_REPROCESO => 'Reproceso',
            InventoryManualConsumption::TIPO_COMPLETAR_SALDOS => 'Completar saldos',
            default => 'Consumo manual',
        };

        if ($folios === []) {
            return $base;
        }

        $labels = collect($folios)
            ->map(fn (array $f) => trim((string) ($f['folio'] ?? '')) ?: (string) $f['id_g_produccion'])
            ->implode(', ');

        return $base.' · Folios: '.$labels;
    }
}
