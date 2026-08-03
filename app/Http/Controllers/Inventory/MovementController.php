<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryLocation;
use App\Models\InventoryMaterial;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use App\Models\InventoryProduction;
use App\Models\InventoryStockLocation;
use App\Models\InventoryStockPosition;
use App\Models\InventoryWasteReason;
use App\Models\Service;
use App\Services\Inventory\MovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MovementController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $filters = $request->only(['q', 'movement_type_id', 'material_id', 'service_id', 'location_id', 'estado', 'date_from', 'date_to']);

        $movements = InventoryMovement::query()
            ->with([
                'type:id,codigo,nombre',
                'origin:id,nombre',
                'destination:id,nombre',
                'creator:id,name',
                'details.material',
                'details.material.service:id,name',
                'transferUnits.logisticUnit:id,license_plate_number',
                'materialRequest:id,codigo',
                'returnRequest:id,codigo',
            ])
            ->when($filters['q'] ?? null, function ($query, $value) {
                $needle = trim((string) $value);
                $query->where(function ($inner) use ($needle): void {
                    $inner->where('folio', 'like', '%'.$needle.'%')
                        ->orWhere('motivo', 'like', '%'.$needle.'%')
                        ->orWhere('observacion', 'like', '%'.$needle.'%');
                });
            })
            ->when($filters['movement_type_id'] ?? null, fn ($query, $value) => $query->where('movement_type_id', $value))
            ->when($filters['estado'] ?? null, fn ($query, $value) => $query->where('estado', $value))
            ->when($filters['material_id'] ?? null, fn ($query, $value) => $query->whereHas('details', fn ($detail) => $detail->where('material_id', $value)))
            ->when($filters['service_id'] ?? null, fn ($query, $value) => $query->whereHas('details.material', fn ($material) => $material->where('service_id', $value)))
            ->when($filters['location_id'] ?? null, function ($query, $value) {
                $query->where(function ($inner) use ($value): void {
                    $inner->where('origin_location_id', $value)
                        ->orWhere('destination_location_id', $value);
                });
            })
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('fecha_movimiento', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('fecha_movimiento', '<=', $value))
            ->latest('fecha_movimiento')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (InventoryMovement $movement) => [
                'id' => $movement->id,
                'folio' => $movement->folio,
                'fecha_movimiento' => optional($movement->fecha_movimiento)->format('Y-m-d H:i'),
                'estado' => $movement->estado,
                'material_request_codigo' => $movement->materialRequest?->codigo,
                'return_codigo' => $movement->returnRequest?->codigo,
                'grupo' => $movement->materialRequest?->codigo ?? $movement->returnRequest?->codigo,
                'grupo_tipo' => $movement->materialRequest ? 'solicitud' : ($movement->returnRequest ? 'devolucion' : null),
                'tipo' => $movement->type?->nombre,
                'tipo_codigo' => $movement->type?->codigo,
                'origen' => $movement->origin?->nombre,
                'destino' => $movement->destination?->nombre,
                'motivo' => $movement->motivo,
                'observacion' => $movement->observacion,
                'creador' => $movement->creator?->name,
                'receipt_hash' => $movement->receipt_hash,
                'ledger_hash' => $movement->ledger_hash,
                'details' => $movement->details->map(fn ($detail) => [
                    'id' => $detail->id,
                    'material' => trim(($detail->material?->codigo ?? '').' · '.($detail->material?->nombre ?? '')),
                    'service' => $detail->material?->service?->name,
                    'cantidad' => (float) $detail->cantidad,
                    'sentido' => $detail->sentido,
                    'position_id' => data_get($detail->metadata, 'position_id'),
                    'position_label' => $this->formatPositionLabel((array) ($detail->metadata ?? [])),
                ])->values(),
                'transfer_units' => $movement->transferUnits->map(fn ($unit) => [
                    'id' => $unit->id,
                    'license_plate_number' => $unit->logisticUnit?->license_plate_number,
                    'quantity' => (float) $unit->quantity,
                    'status' => $unit->status,
                    'rejection_reason' => $unit->rejection_reason,
                    'received_at' => optional($unit->received_at)->format('Y-m-d H:i'),
                    'returned_at' => optional($unit->returned_at)->format('Y-m-d H:i'),
                    'position_count' => count((array) data_get($unit->metadata, 'position_snapshots', [])),
                    'position_labels' => $this->formatTransferUnitPositionLabels((array) ($unit->metadata ?? [])),
                    'origin_snapshot' => data_get($unit->metadata, 'origin_location_snapshot.codigo'),
                    'destination_snapshot' => data_get($unit->metadata, 'destination_location_snapshot.codigo'),
                ])->values(),
            ]);

        return Inertia::render('Inventory/Movements/Index', [
            'movements' => $movements,
            'filters' => $filters,
            'movementTypes' => InventoryMovementType::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'requiere_origen', 'requiere_destino', 'requiere_motivo', 'permite_direcciones_mixtas']),
            'movementStatuses' => ['borrador', 'aplicado', 'confirmado'],
            'locations' => InventoryLocation::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'materials' => InventoryMaterial::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
            'wasteReasons' => InventoryWasteReason::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'productions' => InventoryProduction::query()->with('packaging:id,nombre')->latest('fecha')->limit(50)->get()->map(fn (InventoryProduction $production) => [
                'id' => $production->id,
                'label' => '#'.$production->id.' · '.optional($production->fecha)->format('Y-m-d').' · '.$production->linea.' · '.($production->packaging?->nombre ?? '-'),
            ]),
        ]);
    }

    public function store(Request $request, MovementService $movementService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $positionRules = ['nullable', 'integer'];
        if (Schema::hasTable('inventory_stock_positions')) {
            $positionRules[] = 'exists:inventory_stock_positions,id';
        }

        $data = $request->validate([
            'movement_type_id' => ['required', 'exists:inventory_movement_types,id'],
            'fecha_movimiento' => ['required', 'date'],
            'origin_location_id' => ['nullable', 'exists:inventory_locations,id'],
            'destination_location_id' => ['nullable', 'exists:inventory_locations,id'],
            'referencia_tipo' => ['nullable', 'string', 'max:50'],
            'referencia_id' => ['nullable', 'integer'],
            'motivo' => ['nullable', 'string', 'max:150'],
            'observacion' => ['nullable', 'string'],
            'waste_reason_id' => ['nullable', 'exists:inventory_waste_reasons,id'],
            'scan_session_uuid' => ['nullable', 'uuid'],
            'requires_photo_evidence' => ['boolean'],
            'metadata' => ['nullable', 'array'],
            'apply_now' => ['boolean'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.material_id' => ['required', 'exists:inventory_materials,id'],
            'details.*.sentido' => ['nullable', 'in:salida,entrada'],
            'details.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'details.*.costo_referencial' => ['nullable', 'numeric', 'gte:0'],
            'details.*.observacion' => ['nullable', 'string'],
            'details.*.position_id' => $positionRules,
        ]);

        $movementService->create($data, (int) $request->user()->id, (bool) ($data['apply_now'] ?? false));

        return back()->with('success', 'Movimiento registrado.');
    }

    public function apply(Request $request, InventoryMovement $movement, MovementService $movementService): RedirectResponse
    {
        $this->authorizeInventory($request);

        try {
            $movementService->apply($movement, (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->with('error', $this->validationMessage($exception));
        }

        return back()->with('success', 'Movimiento aplicado.');
    }

    public function confirm(Request $request, InventoryMovement $movement, MovementService $movementService): RedirectResponse
    {
        $this->authorizeInventory($request);

        try {
            $data = $request->validate([
                'transfer_unit_ids' => ['nullable', 'array'],
                'transfer_unit_ids.*' => ['integer'],
            ]);

            $movementService->confirmReceipt($movement, (int) $request->user()->id, $data['transfer_unit_ids'] ?? null);
        } catch (ValidationException $exception) {
            return back()->with('error', $this->validationMessage($exception));
        }

        return back()->with('success', 'Recepción confirmada.');
    }

    public function reject(Request $request, InventoryMovement $movement, MovementService $movementService): RedirectResponse
    {
        $this->authorizeInventory($request);

        try {
            $data = $request->validate([
                'transfer_unit_ids' => ['required', 'array', 'min:1'],
                'transfer_unit_ids.*' => ['integer'],
                'reason' => ['required', 'string', 'max:500'],
            ]);

            $movementService->rejectTransferUnits(
                $movement,
                (int) $request->user()->id,
                $data['transfer_unit_ids'],
                trim((string) $data['reason']),
            );
        } catch (ValidationException $exception) {
            return back()->with('error', $this->validationMessage($exception));
        }

        return back()->with('success', 'Recepción rechazada. El retorno quedó pendiente para origen.');
    }

    public function stockReference(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'origin_location_id' => ['required', 'exists:inventory_locations,id'],
            'material_id' => ['required', 'exists:inventory_materials,id'],
        ]);

        $stock = InventoryStockLocation::query()
            ->where('location_id', $data['origin_location_id'])
            ->where('material_id', $data['material_id'])
            ->value('stock_actual');

        $positions = [];

        if (Schema::hasTable('inventory_stock_positions')) {
            $positions = InventoryStockPosition::query()
                ->with(['location:id,codigo,nombre', 'logisticUnit:id,license_plate_number'])
                ->where('location_id', $data['origin_location_id'])
                ->where('material_id', $data['material_id'])
                ->where('quantity', '>', 0)
                ->orderByDesc('quantity')
                ->orderBy('id')
                ->get()
                ->map(fn (InventoryStockPosition $position) => [
                    'id' => $position->id,
                    'quantity' => round((float) $position->quantity, 4),
                    'lot_code' => $position->lot_code,
                    'status' => $position->status,
                    'location' => $position->location ? [
                        'id' => $position->location->id,
                        'codigo' => $position->location->codigo,
                        'nombre' => $position->location->nombre,
                    ] : null,
                    'logistic_unit' => $position->logisticUnit ? [
                        'id' => $position->logisticUnit->id,
                        'license_plate_number' => $position->logisticUnit->license_plate_number,
                    ] : null,
                ])
                ->values()
                ->all();
        }

        return response()->json([
            'origin_location_id' => (int) $data['origin_location_id'],
            'material_id' => (int) $data['material_id'],
            'stock_actual' => round((float) ($stock ?? 0), 4),
            'positions' => $positions,
        ]);
    }

    private function validationMessage(ValidationException $exception): string
    {
        return collect($exception->errors())
            ->flatten()
            ->filter()
            ->first() ?? $exception->getMessage();
    }

    private function formatPositionLabel(array $metadata): ?string
    {
        $positionId = $metadata['position_id'] ?? null;
        if (! $positionId) {
            return null;
        }

        $parts = ["Posición #{$positionId}"];

        if ($lpn = data_get($metadata, 'position_logistic_unit_snapshot.license_plate_number')) {
            $parts[] = $lpn;
        }

        if ($locationCode = data_get($metadata, 'position_location_snapshot.codigo')) {
            $parts[] = $locationCode;
        }

        if ($lotCode = $metadata['position_lot_code_snapshot'] ?? null) {
            $parts[] = $lotCode;
        }

        return implode(' · ', $parts);
    }

    private function formatTransferUnitPositionLabels(array $metadata): array
    {
        return collect((array) ($metadata['position_snapshots'] ?? []))
            ->map(function (array $snapshot): string {
                $parts = [];

                if ($positionId = $snapshot['position_id'] ?? null) {
                    $parts[] = "Posición #{$positionId}";
                }

                if ($locationCode = data_get($snapshot, 'location_snapshot.codigo')) {
                    $parts[] = $locationCode;
                }

                if ($lotCode = $snapshot['lot_code'] ?? null) {
                    $parts[] = $lotCode;
                }

                $parts[] = number_format((float) ($snapshot['quantity'] ?? 0), 4, ',', '.');

                return implode(' · ', array_filter($parts));
            })
            ->filter()
            ->values()
            ->all();
    }
}
