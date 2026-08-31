<?php

namespace App\Services\Inventory;

use App\Models\InventoryAutoConsumptionFolio;
use App\Models\InventoryConsumptionOrigin;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use App\Models\InventoryPackaging;
use App\Models\InventoryProduction;
use App\Models\InventoryStockLocation;
use App\Models\InventoryStockPosition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AutoConsumptionService
{
    public function __construct(
        private readonly MovementService $movementService,
        private readonly TheoreticalConsumptionService $theoreticalService,
        private readonly SalidasFolioProvider $folioProvider,
    ) {
    }

    public function run(int $limit, ?string $onlyFolio = null, bool $dryRun = false): array
    {
        if ($dryRun) {
            DB::connection()->beginTransaction();
            $results = $this->processWindow($limit, $onlyFolio);
            DB::connection()->rollBack();

            return $results;
        }

        return $this->processWindow($limit, $onlyFolio);
    }

    /**
     * Reintenta un folio fallido o en borrador reutilizando la producción
     * y el movimiento ya creados (evita duplicados).
     */
    public function retry(InventoryAutoConsumptionFolio $control): array
    {
        if (in_array($control->estado, ['aplicado', 'temporal'], true)) {
            return $this->resultShape($control, $control->estado, null);
        }

        $folio = (array) $control->raw_payload;
        $packaging = InventoryPackaging::query()
            ->where('codigo', $folio['c_embalaje'] ?? $control->c_embalaje)
            ->where('activo', true)
            ->first();

        if (! $packaging) {
            $this->updateControl($control, ['packaging_id' => null], 'No se encontró embalaje con código '.($folio['c_embalaje'] ?? $control->c_embalaje), 'sin_embalaje');

            return $this->resultShape($control, 'sin_embalaje', $control->detalle_estado);
        }

        $pallets = 0.0;
        if ((float) $packaging->cantidad_cajas > 0) {
            $pallets = round((float) ($folio['cantidad'] ?? $control->cantidad) / (float) $packaging->cantidad_cajas, 4);
        }

        $calibres = ! empty($folio['n_calibre']) ? [trim((string) $folio['n_calibre'])] : null;

        $preview = $this->theoreticalService->preview(
            (int) $packaging->id,
            $folio['fecha_produccion'] ?? $control->fecha_produccion?->toDateString(),
            (float) ($folio['cantidad'] ?? $control->cantidad),
            $pallets,
            null,
            $calibres
        );

        if (! $preview['sheet']) {
            $this->updateControl($control, ['packaging_id' => $packaging->id], 'No existe ficha técnica vigente para '.$packaging->nombre, 'sin_ficha');

            return $this->resultShape($control, 'sin_ficha', $control->detalle_estado);
        }

        $rows = array_values(array_filter($preview['rows'], fn (array $row) => (float) $row['theoretical_total'] > 0 && (int) $row['material_id'] > 0));

        if ($rows === []) {
            $this->updateControl($control, ['packaging_id' => $packaging->id], 'La ficha de '.$packaging->nombre.' no define materiales consumibles.', 'sin_ficha');

            return $this->resultShape($control, 'sin_ficha', $control->detalle_estado);
        }

        $temporal = (bool) $control->es_temporal || $this->folioIsTemporal((string) $control->folio);
        $origin = $this->resolveOrigin($folio);
        $userId = $this->systemUserId();

        $production = $control->production
            ?? $this->createProduction($folio, $packaging, $pallets, $userId, $temporal);

        $movement = $control->movement;

        if ($movement && $movement->estado === 'aplicado') {
            $estado = $temporal ? 'temporal' : 'aplicado';
            $detalle = null;
        } elseif ($temporal) {
            if ($movement && $movement->estado === 'borrador' && $origin) {
                try {
                    $this->movementService->apply($movement, $userId);
                    $estado = 'temporal';
                    $detalle = null;
                } catch (\Throwable $e) {
                    $estado = 'borrador';
                    $detalle = $e->getMessage();
                }
            } elseif (! $origin) {
                $estado = 'borrador';
                $detalle = 'No se encontró ubicación de origen para línea/turno del folio.';
            } else {
                [$movement, $estado, $detalle] = $this->createTemporalMovement($production, $packaging, $rows, $origin, $userId);
            }
        } elseif (! $origin) {
            if (! $movement) {
                [, $estado, $detalle] = $this->createConsumptionMovement($production, $packaging, $rows, null, $userId);
            } else {
                $estado = 'borrador';
                $detalle = 'No se encontró ubicación de origen para línea/turno del folio.';
            }
        } elseif ($movement) {
            try {
                $this->movementService->apply($movement, $userId);
                $estado = 'aplicado';
                $detalle = null;
            } catch (\Throwable $e) {
                $estado = 'borrador';
                $detalle = $e->getMessage();
            }
        } else {
            [$movement, $estado, $detalle] = $this->createConsumptionMovement($production, $packaging, $rows, $origin, $userId);
        }

        $this->updateControl(
            $control,
            [
                'packaging_id' => $packaging->id,
                'production_id' => $production->id,
                'movement_id' => $movement?->id,
                'origin_location_id' => $origin?->id,
            ],
            $detalle,
            $estado
        );

        return $this->resultShape($control, $estado, $detalle);
    }

    /**
     * @return list<array>
     */
    private function processWindow(int $limit, ?string $onlyFolio): array
    {
        $rows = $this->folioProvider->latest($limit);
        $results = [];

        foreach ($rows as $row) {
            $folio = $this->normalize($row);

            if ($onlyFolio !== null && $folio['folio'] !== $onlyFolio) {
                continue;
            }

            if ($this->alreadyProcessed($folio['id_g_produccion'], $folio['folio'])) {
                continue;
            }

            $results[] = $this->processFolio($folio);
        }

        return $results;
    }

    private function processFolio(array $folio): array
    {
        $packaging = InventoryPackaging::query()
            ->where('codigo', $folio['c_embalaje'])
            ->where('activo', true)
            ->first();

        if (! $packaging) {
            return $this->record([...$folio, 'estado' => 'sin_embalaje'], detail: 'No se encontró embalaje con código '.$folio['c_embalaje']);
        }

        $pallets = 0.0;
        if ((float) $packaging->cantidad_cajas > 0) {
            $pallets = round($folio['cantidad'] / (float) $packaging->cantidad_cajas, 4);
        }

        $calibres = $folio['n_calibre'] !== null && trim($folio['n_calibre']) !== ''
            ? [trim($folio['n_calibre'])]
            : null;

        $preview = $this->theoreticalService->preview(
            (int) $packaging->id,
            $folio['fecha_produccion'],
            $folio['cantidad'],
            $pallets,
            null,
            $calibres
        );

        if (! $preview['sheet']) {
            return $this->record([
                ...$folio,
                'packaging_id' => $packaging->id,
                'cantidad_pallets' => $pallets,
                'estado' => 'sin_ficha',
            ], detail: 'No existe ficha técnica vigente para '.$packaging->nombre);
        }

        $rows = array_values(array_filter($preview['rows'], fn (array $row) => (float) $row['theoretical_total'] > 0 && (int) $row['material_id'] > 0));

        if ($rows === []) {
            return $this->record([
                ...$folio,
                'packaging_id' => $packaging->id,
                'cantidad_pallets' => $pallets,
                'estado' => 'sin_ficha',
            ], detail: 'La ficha de '.$packaging->nombre.' no define materiales consumibles.');
        }

        $temporal = $this->isTemporal($folio);
        $origin = $this->resolveOrigin($folio);
        $userId = $this->systemUserId();

        $production = $this->createProduction($folio, $packaging, $pallets, $userId, $temporal);

        if ($temporal) {
            [$movement, $estado, $detalle] = $this->createTemporalMovement($production, $packaging, $rows, $origin, $userId);
        } else {
            [$movement, $estado, $detalle] = $this->createConsumptionMovement($production, $packaging, $rows, $origin, $userId);
        }

        return $this->record([
            ...$folio,
            'packaging_id' => $packaging->id,
            'production_id' => $production->id,
            'movement_id' => $movement?->id,
            'origin_location_id' => $origin?->id,
            'cantidad_pallets' => $pallets,
            'es_temporal' => $temporal,
            'estado' => $estado,
            'detalle_estado' => $detalle,
        ]);
    }

    private function createProduction(array $folio, InventoryPackaging $packaging, float $pallets, int $userId, bool $temporal = false): InventoryProduction
    {
        return InventoryProduction::create([
            'fecha' => $folio['fecha_produccion'],
            'turno' => $folio['n_turno'],
            'linea' => $folio['n_linea_proceso'],
            'especie' => $folio['n_especie'],
            'variedad' => $folio['n_variedad'],
            'packaging_id' => $packaging->id,
            'cantidad_cajas' => $folio['cantidad'],
            'cantidad_pallets' => $pallets,
            'referencia_tipo' => 'produccion_folio',
            'referencia_id' => (int) $folio['id_g_produccion'],
            'observacion' => 'Consumo automático folio '.$folio['folio'].($temporal ? ' (temporal)' : ''),
            'created_by' => $userId,
            'metadata' => [
                ...$folio,
                'tipo_consumo' => $temporal ? 'temporal' : 'normal',
            ],
        ]);
    }

    /**
     * @return array{0: ?InventoryMovement, 1: string, 2: ?string}
     */
    private function createTemporalMovement(
        InventoryProduction $production,
        InventoryPackaging $packaging,
        array $rows,
        ?InventoryLocation $origin,
        int $userId,
    ): array {
        if (! $origin) {
            return [null, 'borrador', 'No se encontró ubicación de origen para línea/turno del folio.'];
        }

        $consumeType = InventoryMovementType::query()->where('codigo', 'CONSUMO')->firstOrFail();

        $movement = InventoryMovement::create([
            'folio' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
            'movement_type_id' => $consumeType->id,
            'fecha_movimiento' => $production->fecha,
            'origin_location_id' => $origin->id,
            'referencia_tipo' => 'production',
            'referencia_id' => $production->id,
            'motivo' => 'Consumo temporal de folio',
            'observacion' => 'Folio '.$production->metadata['folio'].' · '.$packaging->nombre,
            'estado' => 'borrador',
            'created_by' => $userId,
            'metadata' => [
                'workflow' => 'auto_consumption',
                'tipo_consumo' => 'temporal',
                'allow_negative_stock' => true,
                'id_g_produccion' => $production->metadata['id_g_produccion'],
                'folio' => $production->metadata['folio'],
                'n_embalaje' => $production->metadata['n_embalaje'],
                'c_embalaje' => $packaging->codigo,
                'n_calibre' => $production->metadata['n_calibre'],
                'n_linea_proceso' => $production->metadata['n_linea_proceso'],
                'n_turno' => $production->metadata['n_turno'],
            ],
        ]);

        foreach ($rows as $row) {
            $movement->details()->create([
                'material_id' => (int) $row['material_id'],
                'sentido' => 'salida',
                'cantidad' => round((float) $row['theoretical_total'], 4),
            ]);
        }

        try {
            $this->movementService->apply($movement, $userId);

            return [$movement, 'temporal', null];
        } catch (\Throwable $e) {
            return [$movement, 'borrador', $e->getMessage()];
        }
    }

    /**
     * @return array{0: ?InventoryMovement, 1: string, 2: ?string}
     */
    private function createConsumptionMovement(
        InventoryProduction $production,
        InventoryPackaging $packaging,
        array $rows,
        ?InventoryLocation $origin,
        int $userId,
    ): array {
        $consumeType = InventoryMovementType::query()->where('codigo', 'CONSUMO')->firstOrFail();

        $attributes = [
            'movement_type_id' => $consumeType->id,
            'fecha_movimiento' => $production->fecha,
            'origin_location_id' => $origin?->id,
            'referencia_tipo' => 'production',
            'referencia_id' => $production->id,
            'motivo' => 'Consumo automático de folio',
            'observacion' => 'Folio '.$production->metadata['folio'].' · '.$packaging->nombre,
            'metadata' => [
                'workflow' => 'auto_consumption',
                'id_g_produccion' => $production->metadata['id_g_produccion'],
                'folio' => $production->metadata['folio'],
                'n_embalaje' => $production->metadata['n_embalaje'],
                'c_embalaje' => $packaging->codigo,
                'n_calibre' => $production->metadata['n_calibre'],
                'n_linea_proceso' => $production->metadata['n_linea_proceso'],
                'n_turno' => $production->metadata['n_turno'],
            ],
        ];

        if (! $origin) {
            $movement = $this->createBorradorMovement($attributes, $rows, $userId);

            return [$movement, 'borrador', 'No se encontró ubicación de origen para línea/turno del folio.'];
        }

        try {
            $details = $this->buildConsumoDetails($rows, $origin);
        } catch (\Throwable $e) {
            $movement = $this->createBorradorMovement($attributes, $rows, $userId);

            return [$movement, 'borrador', $e->getMessage()];
        }

        try {
            $movement = $this->movementService->create([...$attributes, 'details' => $details], $userId, false);
        } catch (\Throwable $e) {
            $movement = $this->createBorradorMovement($attributes, $rows, $userId);

            return [$movement, 'borrador', $e->getMessage()];
        }

        try {
            $this->movementService->apply($movement, $userId);

            return [$movement, 'aplicado', null];
        } catch (\Throwable $e) {
            return [$movement, 'borrador', $e->getMessage()];
        }
    }

    private function createBorradorMovement(array $attributes, array $rows, int $userId): InventoryMovement
    {
        $movement = InventoryMovement::create([
            ...$attributes,
            'folio' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
            'estado' => 'borrador',
            'created_by' => $userId,
        ]);

        foreach ($rows as $row) {
            $movement->details()->create([
                'material_id' => (int) $row['material_id'],
                'sentido' => 'salida',
                'cantidad' => round((float) $row['theoretical_total'], 4),
            ]);
        }

        return $movement;
    }

    private function buildConsumoDetails(array $rows, InventoryLocation $origin): array
    {
        $details = [];
        $hasPositionsTable = Schema::hasTable('inventory_stock_positions');

        foreach ($rows as $row) {
            $materialId = (int) $row['material_id'];
            $quantity = round((float) $row['theoretical_total'], 4);

            if ($materialId <= 0 || $quantity <= 0) {
                continue;
            }

            $materialName = (string) ($row['material_nombre'] ?? 'material');

            if ($hasPositionsTable) {
                $positions = InventoryStockPosition::query()
                    ->where('location_id', $origin->id)
                    ->where('material_id', $materialId)
                    ->where('quantity', '>', 0)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($positions->isNotEmpty()) {
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
                            'detalles' => "Stock insuficiente en posiciones para {$materialName}. Faltan {$remaining} unidades.",
                        ]);
                    }

                    continue;
                }
            }

            $stock = InventoryStockLocation::query()
                ->where('location_id', $origin->id)
                ->where('material_id', $materialId)
                ->lockForUpdate()
                ->first();
            $available = (float) ($stock?->stock_actual ?? 0);

            if (! $origin->permite_stock_negativo && $available + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'detalles' => "Stock insuficiente para {$materialName}. Disponible: {$available}. Requerido: {$quantity}.",
                ]);
            }

            $details[] = [
                'material_id' => $materialId,
                'cantidad' => $quantity,
                'sentido' => 'salida',
            ];
        }

        if ($details === []) {
            throw ValidationException::withMessages([
                'detalles' => 'La ficha no define materiales consumibles con cantidad mayor a cero.',
            ]);
        }

        return $details;
    }

    private function resolveOrigin(array $folio): ?InventoryLocation
    {
        $linea = trim((string) ($folio['n_linea_proceso'] ?? ''));
        $turno = trim((string) ($folio['n_turno'] ?? ''));

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

    private function isTemporal(array $folio): bool
    {
        return $this->folioIsTemporal((string) ($folio['folio'] ?? ''));
    }

    private function folioIsTemporal(string $folio): bool
    {
        return Str::endsWith(mb_strtoupper(trim($folio)), 'T');
    }

    private function alreadyProcessed(int|string $idGProduccion, string $folio): bool
    {
        return InventoryAutoConsumptionFolio::query()
            ->where('id_g_produccion', (int) $idGProduccion)
            ->where('folio', $folio)
            ->exists();
    }

    private function normalize($row): array
    {
        return [
            'id_g_produccion' => (int) ($row->id_g_produccion ?? 0),
            'folio' => trim((string) ($row->folio ?? '')),
            'numero_g_produccion' => isset($row->numero_g_produccion) ? (string) $row->numero_g_produccion : null,
            'c_embalaje' => trim((string) ($row->c_embalaje ?? '')),
            'n_embalaje' => isset($row->n_embalaje) ? (string) $row->n_embalaje : null,
            'cantidad' => (float) ($row->cantidad ?? 0),
            'peso_neto' => isset($row->peso_neto) ? (float) $row->peso_neto : null,
            'n_linea_proceso' => isset($row->n_linea_proceso) ? trim((string) $row->n_linea_proceso) : null,
            'n_turno' => isset($row->n_turno) ? trim((string) $row->n_turno) : null,
            'n_calibre' => isset($row->n_calibre) ? trim((string) $row->n_calibre) : null,
            'n_especie' => isset($row->n_especie) ? (string) $row->n_especie : null,
            'n_variedad' => isset($row->n_variedad) ? (string) $row->n_variedad : null,
            'fecha_produccion' => isset($row->fecha_produccion)
                ? substr((string) $row->fecha_produccion, 0, 10)
                : now()->toDateString(),
        ];
    }

/**
     * @param  array<string, mixed>  $data
     */
    private function record(array $data, ?string $detail = null): array
    {
        $movementId = $data['movement_id'] ?? null;
        $productionId = $data['production_id'] ?? null;
        $packagingId = $data['packaging_id'] ?? null;
        $originLocationId = $data['origin_location_id'] ?? null;
        $estado = (string) $data['estado'];
        $esTemporal = (bool) ($data['es_temporal'] ?? false);

        InventoryAutoConsumptionFolio::create([
            'id_g_produccion' => (int) $data['id_g_produccion'],
            'folio' => (string) $data['folio'],
            'es_temporal' => $esTemporal,
            'numero_g_produccion' => $data['numero_g_produccion'] ?? null,
            'c_embalaje' => $data['c_embalaje'] ?? null,
            'n_embalaje' => $data['n_embalaje'] ?? null,
            'cantidad' => (float) ($data['cantidad'] ?? 0),
            'peso_neto' => $data['peso_neto'] ?? null,
            'n_linea_proceso' => $data['n_linea_proceso'] ?? null,
            'n_turno' => $data['n_turno'] ?? null,
            'n_calibre' => $data['n_calibre'] ?? null,
            'n_especie' => $data['n_especie'] ?? null,
            'n_variedad' => $data['n_variedad'] ?? null,
            'fecha_produccion' => $data['fecha_produccion'] ?? null,
            'packaging_id' => $packagingId,
            'production_id' => $productionId,
            'movement_id' => $movementId,
            'origin_location_id' => $originLocationId,
            'estado' => $estado,
            'detalle_estado' => $detail ?? ($data['detalle_estado'] ?? null),
            'raw_payload' => collect($data)->except([
                'production_id', 'movement_id', 'origin_location_id', 'packaging_id', 'estado', 'detalle_estado', 'cantidad_pallets', 'es_temporal',
            ])->all(),
            'processed_at' => now(),
        ]);

        return [
            'id_g_produccion' => (int) $data['id_g_produccion'],
            'folio' => (string) $data['folio'],
            'c_embalaje' => $data['c_embalaje'] ?? null,
            'n_embalaje' => $data['n_embalaje'] ?? null,
            'cantidad' => (float) ($data['cantidad'] ?? 0),
            'linea' => $data['n_linea_proceso'] ?? null,
            'turno' => $data['n_turno'] ?? null,
            'calibre' => $data['n_calibre'] ?? null,
            'packaging' => null,
            'materiales' => 0,
            'origen' => null,
            'estado' => $estado,
            'detalle_estado' => $detail ?? ($data['detalle_estado'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateControl(InventoryAutoConsumptionFolio $control, array $data, ?string $detail, string $estado): void
    {
        $control->forceFill([...$data, 'estado' => $estado, 'detalle_estado' => $detail, 'processed_at' => now()])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function resultShape(InventoryAutoConsumptionFolio $control, string $estado, ?string $detalle): array
    {
        return [
            'id_g_produccion' => (int) $control->id_g_produccion,
            'folio' => (string) $control->folio,
            'c_embalaje' => $control->c_embalaje,
            'n_embalaje' => $control->n_embalaje,
            'cantidad' => (float) $control->cantidad,
            'linea' => $control->n_linea_proceso,
            'turno' => $control->n_turno,
            'calibre' => $control->n_calibre,
            'es_temporal' => (bool) $control->es_temporal,
            'estado' => $estado,
            'detalle_estado' => $detalle,
        ];
    }

    public function systemUserId(): int
    {
        $email = (string) config('services.termo.auto_consumption.system_user_email', 'sistema.auto@appgreenex.test');
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            return (int) $user->id;
        }

        $admin = User::query()->where('status', 'Admin')->orWhere('status', 'Administrador')->first();

        if ($admin) {
            return (int) $admin->id;
        }

        $user = User::create([
            'name' => 'Sistema Consumo Automático',
            'email' => $email,
            'password' => Str::random(40),
        ]);

        return (int) $user->id;
    }
}