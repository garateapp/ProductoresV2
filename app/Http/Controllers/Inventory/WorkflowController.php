<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Http\Requests\Inventory\StoreTransferScanRequest;
use App\Http\Requests\Inventory\StoreWasteScanRequest;
use App\Models\InventoryLocation;
use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMaterial;
use App\Models\InventoryMovementType;
use App\Models\InventoryStockPosition;
use App\Models\InventoryWasteReason;
use App\Notifications\InventoryTransferDispatchedNotification;
use App\Services\Inventory\LogisticUnitService;
use App\Services\Inventory\MovementService;
use App\Services\Inventory\ScanResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\InventoryMaterialRequest;
use App\Models\InventoryWasteType;

class WorkflowController extends Controller{
     use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $materialRequests = InventoryMaterialRequest::query()
            ->with(['originLocation:id,nombre', 'destinationLocation:id,nombre', 'items.material:id,codigo,nombre'])
            ->whereIn('estado', ['aprobado', 'completado'])
            ->latest()
            ->get()
            ->map(fn($req) => [
                'id' => $req->id,
                'label' => "{$req->codigo} · ".($req->originLocation?->nombre ?? 'N/A')." -> ".($req->destinationLocation?->nombre ?? 'N/A'),
                'origin_location_id' => $req->origin_location_id,
                'destination_location_id' => $req->destination_location_id,
                'items' => $req->items->map(fn($item) => [
                    'id' => $item->id,
                    'material' => $item->material ? [
                        'id' => $item->material->id,
                        'codigo' => $item->material->codigo,
                        'nombre' => $item->material->nombre,
                    ] : null,
                    'cantidad_solicitada' => $item->cantidad_solicitada,
                    'notas' => $item->notas,
                ]),
            ]);

        return Inertia::render('Inventory/Workflows/Scan', [
            'locations' => InventoryLocation::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'scan_code', 'nombre', 'path_code']),
            'materials' => InventoryMaterial::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'wasteReasons' => InventoryWasteReason::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'wasteTypes' => InventoryWasteType::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'materialRequests' => $materialRequests,
        ]);
    }

    public function transfer(StoreTransferScanRequest $request, MovementService $movementService, ScanResolutionService $scanResolutionService, LogisticUnitService $logisticUnitService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validated();
        $destination = filled($data['destination_location_id'] ?? null)
            ? InventoryLocation::query()->find((int) $data['destination_location_id'])
            : $scanResolutionService->resolveLocation($data['destination_code']);

        if (! $destination) {
            throw ValidationException::withMessages([
                'destination_code' => 'No fue posible resolver la ubicación de destino.',
            ]);
        }

        $transferType = InventoryMovementType::query()->where('codigo', 'TRANSFERENCIA')->firstOrFail();
        $codes = collect($data['logistic_unit_codes'] ?? [])
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->values();

        $units = $codes->map(function (string $code) use ($logisticUnitService) {
            $unit = $logisticUnitService->findByCode($code);

            if (! $unit) {
                throw ValidationException::withMessages([
                    'logistic_unit_codes' => "No fue posible resolver el pallet {$code}.",
                ]);
            }

            return $unit;
        });

        $transferItemByCode = collect($data['transfer_items'] ?? [])
            ->mapWithKeys(fn (array $item) => [trim((string) $item['logistic_unit_code']) => $item]);

        $origins = $units
            ->pluck('current_location_id')
            ->unique()
            ->values();

        if ($units->contains(fn ($unit) => blank($unit->current_location_id))) {
            throw ValidationException::withMessages([
                'logistic_unit_codes' => 'Todos los pallets deben tener ubicación actual asignada.',
            ]);
        }

        if ($origins->count() !== 1) {
            throw ValidationException::withMessages([
                'logistic_unit_codes' => 'Por ahora el traslado agrupado requiere que todos los pallets pertenezcan a la misma ubicación de origen.',
            ]);
        }

        $origin = InventoryLocation::query()->findOrFail((int) $origins->first());

        if ((int) $origin->id === (int) $destination->id) {
            throw ValidationException::withMessages([
                'destination_code' => 'La ubicación destino debe ser distinta al origen resuelto desde los pallets.',
            ]);
        }

        $transferItems = $units->map(function ($unit) use ($transferItemByCode, $origin) {
            $item = $transferItemByCode->get($unit->license_plate_number);
            $positionId = (int) ($item['position_id'] ?? 0);
            $quantity = (float) ($unit->available_quantity ?? 0);
            $metadata = [
                'license_plate_number' => $unit->license_plate_number,
            ];

            if ($transferItemByCode->isNotEmpty() && ! $item) {
                throw ValidationException::withMessages([
                    'transfer_items' => "Falta la selección de posición para el pallet {$unit->license_plate_number}.",
                ]);
            }

            if (Schema::hasTable('inventory_stock_positions')) {
                $positions = InventoryStockPosition::query()
                    ->where('logistic_unit_id', $unit->id)
                    ->where('location_id', $origin->id)
                    ->where('quantity', '>', 0)
                    ->orderBy('id')
                    ->get();

                if ($transferItemByCode->isNotEmpty() && $positions->count() > 1 && $positionId <= 0) {
                    throw ValidationException::withMessages([
                        'transfer_items' => "Debes seleccionar la posición que estás moviendo para el pallet {$unit->license_plate_number}.",
                    ]);
                }

                if ($transferItemByCode->isNotEmpty() && $positions->count() === 1 && $positionId <= 0) {
                    $positionId = (int) $positions->first()->id;
                }

                if ($positionId > 0) {
                    $position = $positions->firstWhere('id', $positionId);

                    if (! $position) {
                        throw ValidationException::withMessages([
                            'transfer_items' => "La posición seleccionada no pertenece al pallet {$unit->license_plate_number} en su ubicación actual.",
                        ]);
                    }

                    $quantity = (float) ($item['quantity'] ?? $position->quantity);

                    if ($quantity <= 0 || $quantity > (float) $position->quantity) {
                        throw ValidationException::withMessages([
                            'transfer_items' => "La cantidad seleccionada para {$unit->license_plate_number} supera el stock de la posición.",
                        ]);
                    }

                    $metadata = [
                        ...$metadata,
                        'requested_position_id' => $position->id,
                        'requested_quantity' => round($quantity, 4),
                        'partial_transfer' => [
                            'position_id' => $position->id,
                            'quantity' => round($quantity, 4),
                        ],
                    ];
                }
            }

            return [
                'unit' => $unit,
                'quantity' => round($quantity, 4),
                'metadata' => $metadata,
            ];
        });

        $movement = $movementService->create([
            'movement_type_id' => $transferType->id,
            'fecha_movimiento' => $data['fecha_movimiento'] ?? now()->format('Y-m-d H:i:s'),
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'material_request_id' => $data['material_request_id'] ?? null,
            'motivo' => 'Traslado por escaneo',
            'observacion' => $data['observacion'] ?? null,
            'scan_session_uuid' => $data['scan_session_uuid'] ?? null,
            'metadata' => [
                'device_code' => $data['device_code'] ?? null,
                'workflow' => 'transfer_scan',
                'logistic_unit_codes' => $codes->all(),
                'transfer_items' => $transferItems->map(fn (array $item) => [
                    'logistic_unit_code' => $item['unit']->license_plate_number,
                    'quantity' => $item['quantity'],
                    'position_id' => $item['metadata']['requested_position_id'] ?? null,
                ])->values()->all(),
            ],
            'details' => $transferItems
                ->groupBy(fn (array $item) => $item['unit']->material_id)
                ->map(fn ($group, $materialId) => [
                    'material_id' => (int) $materialId,
                    'cantidad' => (float) $group->sum(fn (array $item) => (float) $item['quantity']),
                    'sentido' => 'salida',
                ])
                ->values()
                ->all(),
        ], (int) $request->user()->id, false);

        foreach ($transferItems as $item) {
            $unit = $item['unit'];

            $movement->transferUnits()->create([
                'logistic_unit_id' => $unit->id,
                'material_id' => $unit->material_id,
                'origin_location_id' => $origin->id,
                'destination_location_id' => $destination->id,
                'quantity' => (float) $item['quantity'],
                'status' => 'pending',
                'metadata' => $item['metadata'],
            ]);
        }

        $movement = $movementService->apply($movement, (int) $request->user()->id);

        $movement->loadMissing(['destination.assignedUsers', 'transferUnits.logisticUnit']);
        $destination->assignedUsers
            ->filter(fn ($user) => $user->id !== $request->user()->id)
            ->each(fn ($user) => $user->notify(new InventoryTransferDispatchedNotification($movement)));

        return back()->with('success', 'Traslado registrado y notificado al destino.');
    }

    public function transferReference(Request $request, LogisticUnitService $logisticUnitService): JsonResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'logistic_unit_code' => ['required', 'string', 'max:100'],
        ]);

        $unit = $logisticUnitService->findByCode($data['logistic_unit_code']);

        if (! $unit) {
            throw ValidationException::withMessages([
                'logistic_unit_code' => 'No fue posible resolver el pallet/LPN informado.',
            ]);
        }

        $unit->loadMissing([
            'material:id,codigo,nombre',
            'location:id,codigo,scan_code,nombre,path_code',
            'stockPositions' => fn ($query) => $query
                ->with('location:id,codigo,scan_code,nombre,path_code')
                ->where('quantity', '>', 0)
                ->orderByDesc('quantity')
                ->orderBy('id'),
        ]);

        return response()->json([
            'unit' => [
                'id' => $unit->id,
                'license_plate_number' => $unit->license_plate_number,
                'status' => $unit->status,
                'available_quantity' => (float) $unit->available_quantity,
                'spatial_prefix' => $unit->spatial_prefix,
                'spatial_column' => $unit->spatial_column,
                'spatial_row' => $unit->spatial_row,
                'material' => $unit->material ? [
                    'id' => $unit->material->id,
                    'codigo' => $unit->material->codigo,
                    'nombre' => $unit->material->nombre,
                ] : null,
                'location' => $unit->location ? [
                    'id' => $unit->location->id,
                    'codigo' => $unit->location->codigo,
                    'scan_code' => $unit->location->scan_code,
                    'nombre' => $unit->location->nombre,
                    'path_code' => $unit->location->path_code,
                ] : null,
                'positions' => $unit->stockPositions->map(fn (InventoryStockPosition $position) => [
                    'id' => $position->id,
                    'quantity' => round((float) $position->quantity, 4),
                    'lot_code' => $position->lot_code,
                    'status' => $position->status,
                    'location' => $position->location ? [
                        'id' => $position->location->id,
                        'codigo' => $position->location->codigo,
                        'scan_code' => $position->location->scan_code,
                        'nombre' => $position->location->nombre,
                        'path_code' => $position->location->path_code,
                    ] : null,
                ])->values(),
            ],
        ]);
    }

    public function lpnSearch(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'material_id' => ['required', 'integer', 'exists:inventory_materials,id'],
            'location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
        ]);

        $query = InventoryLogisticUnit::query()
            ->with([
                'material:id,codigo,nombre',
                'location:id,codigo,nombre,path_code',
            ])
            ->where('status', 'active')
            ->where('material_id', (int) $data['material_id']);

        if (filled($data['location_id'] ?? null)) {
            $query->where('current_location_id', (int) $data['location_id']);
        }

        $units = $query->limit(30)->get();

        return response()->json([
            'units' => $units->map(fn (InventoryLogisticUnit $unit) => [
                'id' => $unit->id,
                'license_plate_number' => $unit->license_plate_number,
                'status' => $unit->status,
                'available_quantity' => (float) $unit->available_quantity,
                'spatial_prefix' => $unit->spatial_prefix,
                'spatial_column' => $unit->spatial_column,
                'spatial_row' => $unit->spatial_row,
                'material' => $unit->material ? [
                    'id' => $unit->material->id,
                    'codigo' => $unit->material->codigo,
                    'nombre' => $unit->material->nombre,
                ] : null,
                'location' => $unit->location ? [
                    'id' => $unit->location->id,
                    'codigo' => $unit->location->codigo,
                    'nombre' => $unit->location->nombre,
                    'path_code' => $unit->location->path_code,
                ] : null,
            ]),
        ]);
    }

    public function waste(StoreWasteScanRequest $request, MovementService $movementService, ScanResolutionService $scanResolutionService, LogisticUnitService $logisticUnitService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validated();
        $detectedLocation = $scanResolutionService->resolveLocation($data['detected_location_code']);
        $quarantineLocation = filled($data['quarantine_location_code'] ?? null)
            ? $scanResolutionService->resolveLocation($data['quarantine_location_code'])
            : null;

        $unit = null;
        if ($data['is_waste_pallet'] ?? false) {
             $materialId = (int) ($data['material_id'] ?? 0);
             $lpn = $logisticUnitService->suggestLicensePlateNumber($materialId, true);
             $unit = $logisticUnitService->create([
                 'license_plate_number' => $lpn,
                 'material_id' => $materialId,
                 'current_location_id' => $detectedLocation->id,
                 'status' => 'active',
                 'available_quantity' => (float) $data['quantity'],
                 'base_quantity' => (float) $data['quantity'],
             ], (int) $request->user()->id);
        } else {
             $unit = filled($data['logistic_unit_code'] ?? null)
                ? $logisticUnitService->findByCode($data['logistic_unit_code'])
                : null;
        }

        if (! $detectedLocation) {
            throw ValidationException::withMessages([
                'detected_location_code' => 'La ubicación de merma no es válida.',
            ]);
        }

        if (($data['quarantine_location_code'] ?? null) && ! $quarantineLocation) {
            throw ValidationException::withMessages([
                'quarantine_location_code' => 'La ubicación de cuarentena no es válida.',
            ]);
        }

        $materialId = $unit?->material_id ?? (int) $data['material_id'];

        if ($unit && (int) $unit->current_location_id !== (int) $detectedLocation->id) {
            throw ValidationException::withMessages([
                'logistic_unit_code' => 'El pallet no está en la ubicación donde se detecta la merma.',
            ]);
        }

        $wasteType = InventoryMovementType::query()->where('codigo', 'MERMA')->firstOrFail();
        $reason = InventoryWasteReason::query()->findOrFail((int) $data['waste_reason_id']);

        $movement = $movementService->create([
            'movement_type_id' => $wasteType->id,
            'fecha_movimiento' => $data['fecha_movimiento'] ?? now()->format('Y-m-d H:i:s'),
            'origin_location_id' => $detectedLocation->id,
            'destination_location_id' => null,
            'motivo' => $reason->nombre,
            'observacion' => $data['notes'] ?? null,
            'waste_reason_id' => $reason->id,
            'requires_photo_evidence' => (float) $data['quantity'] >= (float) config('inventory.waste.photo_threshold_quantity', 100),
            'scan_session_uuid' => $data['scan_session_uuid'] ?? null,
            'metadata' => [
                'detected_location_id' => $detectedLocation->id,
                'quarantine_location_id' => $quarantineLocation?->id,
                'logistic_unit_id' => $unit?->id,
                'waste_type_id' => $data['waste_type_id'] ?? null,
                'device_code' => $data['device_code'] ?? null,
                'workflow' => 'waste_scan',
            ],
            'details' => [[
                'material_id' => $materialId,
                'position_id' => $data['position_id'] ?? null,
                'cantidad' => (float) $data['quantity'],
                'sentido' => 'salida',
                'observacion' => $data['notes'] ?? null,
            ]],
        ], (int) $request->user()->id, true);

        if ($movement && ($data['waste_type_id'] ?? null)) {
            $movement->wasteRecords()->update(['waste_type_id' => $data['waste_type_id']]);
        }

        return back()->with('success', 'Merma registrada.');
    }

    public function wasteReference(Request $request, ScanResolutionService $scanResolutionService, LogisticUnitService $logisticUnitService): JsonResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'detected_location_code' => ['required', 'string', 'max:100'],
            'logistic_unit_code' => ['nullable', 'string', 'max:100'],
            'material_id' => ['nullable', 'integer', 'exists:inventory_materials,id'],
        ]);

        $detectedLocation = $scanResolutionService->resolveLocation($data['detected_location_code']);
        if (! $detectedLocation) {
            throw ValidationException::withMessages([
                'detected_location_code' => 'La ubicación de merma no es válida.',
            ]);
        }

        $unit = filled($data['logistic_unit_code'] ?? null)
            ? $logisticUnitService->findByCode($data['logistic_unit_code'])
            : null;

        if (($data['logistic_unit_code'] ?? null) && ! $unit) {
            throw ValidationException::withMessages([
                'logistic_unit_code' => 'No fue posible resolver el pallet informado.',
            ]);
        }

        if ($unit && (int) $unit->current_location_id !== (int) $detectedLocation->id) {
            throw ValidationException::withMessages([
                'logistic_unit_code' => 'El pallet no está en la ubicación donde se detecta la merma.',
            ]);
        }

        $materialId = $unit?->material_id ?? ($data['material_id'] ?? null);
        if (! $materialId) {
            return response()->json([
                'location' => [
                    'id' => $detectedLocation->id,
                    'codigo' => $detectedLocation->codigo,
                    'nombre' => $detectedLocation->nombre,
                ],
                'material_id' => null,
                'positions' => [],
            ]);
        }

        $positions = [];
        if (Schema::hasTable('inventory_stock_positions')) {
            $positions = InventoryStockPosition::query()
                ->with(['location:id,codigo,nombre', 'logisticUnit:id,license_plate_number'])
                ->where('location_id', $detectedLocation->id)
                ->where('material_id', (int) $materialId)
                ->when($unit, fn ($query) => $query->where('logistic_unit_id', $unit->id))
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
            'location' => [
                'id' => $detectedLocation->id,
                'codigo' => $detectedLocation->codigo,
                'nombre' => $detectedLocation->nombre,
            ],
            'material_id' => (int) $materialId,
            'logistic_unit_id' => $unit?->id,
            'positions' => $positions,
        ]);
    }
}
