<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Http\Requests\Inventory\StoreLogisticUnitRequest;
use App\Http\Requests\Inventory\UpdateLogisticUnitRequest;
use App\Models\InventoryLocation;
use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMaterial;
use App\Models\InventoryMaterialRequest;
use App\Models\InventoryMovementType;
use App\Models\InventoryStockPosition;
use App\Models\InventoryWasteReason;
use App\Models\InventoryWasteType;
use App\Notifications\InventoryTransferDispatchedNotification;
use App\Services\Inventory\LogisticUnitService;
use App\Services\Inventory\MovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\InventoryTechnicalSheet;

class LogisticUnitController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request, LogisticUnitService $logisticUnitService): Response
    {
        $this->authorizeInventory($request);

        $materialRequests = InventoryMaterialRequest::query()
            ->with(['originLocation:id,nombre', 'destinationLocation:id,nombre', 'items.material:id,codigo,nombre'])
            ->whereIn('estado', ['aprobado', 'completado'])
            ->latest()
            ->get()
            ->map(fn ($req) => [
                'id' => $req->id,
                'label' => "{$req->codigo} · ".($req->originLocation?->nombre ?? 'N/A').' -> '.($req->destinationLocation?->nombre ?? 'N/A'),
                'origin_location_id' => $req->origin_location_id,
                'destination_location_id' => $req->destination_location_id,
                'items' => $req->items->map(fn ($item) => [
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

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'material_id' => (string) $request->input('material_id', ''),
            'location_id' => (string) $request->input('location_id', ''),
            'status' => (string) $request->input('status', ''),
        ];

        $units = InventoryLogisticUnit::query()
            ->with([
                'material:id,codigo,nombre,service_id',
                'material.service:id,name',
                'location:id,codigo,nombre',
                'unit:id,codigo',
                'stockPositions' => fn ($query) => $query
                    ->with('location:id,codigo,nombre')
                    ->orderByDesc('quantity')
                    ->orderBy('id'),
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($inner) use ($filters): void {
                    $inner->where('license_plate_number', 'like', '%'.$filters['q'].'%')
                        ->orWhere('lot_code', 'like', '%'.$filters['q'].'%')
                        ->orWhere('supplier_lot', 'like', '%'.$filters['q'].'%');
                });
            })
            ->when($filters['material_id'] !== '', fn ($query) => $query->where('material_id', $filters['material_id']))
            ->when($filters['location_id'] !== '', fn ($query) => $query->where('current_location_id', $filters['location_id']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (InventoryLogisticUnit $unit): array {
                $positions = $unit->stockPositions->map(fn (InventoryStockPosition $position) => [
                    'id' => $position->id,
                    'quantity' => (float) $position->quantity,
                    'lot_code' => $position->lot_code,
                    'status' => $position->status,
                    'location' => $position->location ? [
                        'id' => $position->location->id,
                        'codigo' => $position->location->codigo,
                        'nombre' => $position->location->nombre,
                    ] : null,
                ])->values();

                $positionQuantity = (float) $positions->sum('quantity');

                return [
                    'id' => $unit->id,
                    'license_plate_number' => $unit->license_plate_number,
                    'dispatch_guide' => $unit->dispatch_guide,
                    'status' => $unit->status,
                    'base_quantity' => (float) $unit->base_quantity,
                    'available_quantity' => $positionQuantity > 0 ? $positionQuantity : (float) $unit->available_quantity,
                    'spatial_prefix' => $unit->spatial_prefix,
                    'spatial_column' => $unit->spatial_column,
                    'spatial_row' => $unit->spatial_row,
                    'lot_code' => $unit->lot_code,
                    'supplier_lot' => $unit->supplier_lot,
                    'material' => $unit->material ? [
                        'id' => $unit->material->id,
                        'codigo' => $unit->material->codigo,
                        'nombre' => $unit->material->nombre,
                        'service_id' => $unit->material->service_id,
                        'service_name' => $unit->material->service?->name,
                    ] : null,
                    'location' => $unit->location ? [
                        'id' => $unit->location->id,
                        'codigo' => $unit->location->codigo,
                        'nombre' => $unit->location->nombre,
                    ] : null,
                    'unit' => $unit->unit?->codigo,
                    'positions' => $positions,
                    'last_moved_at' => optional($unit->last_moved_at)->format('Y-m-d H:i'),
                ];
            });

        $materials = InventoryMaterial::query()
            ->with('service:id,name')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'service_id'])
            ->map(fn (InventoryMaterial $material): array => [
                'id' => $material->id,
                'codigo' => $material->codigo,
                'nombre' => $material->nombre,
                'service_id' => $material->service_id,
                'service_name' => $material->service?->name,
                'suggested_lpn' => $logisticUnitService->suggestLicensePlateNumber((int) $material->id),
            ]);


        return Inertia::render('Inventory/LogisticUnits/Index', [
            'filters' => $filters,
            'units' => $units,
            'materials' => $materials,
            'technicalSheets' => InventoryTechnicalSheet::query()
                ->with(['material:id,nombre', 'unitItems.material:id,nombre'])
                ->where('es_semielaborado', true)
                ->where('activo', true)
                ->get(),
            'locations' => InventoryLocation::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'statuses' => ['active', 'in_transit', 'consumed', 'waste', 'blocked', 'closed'],
            'materialRequests' => $materialRequests,
            'wasteReasons' => InventoryWasteReason::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'wasteTypes' => InventoryWasteType::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function store(StoreLogisticUnitRequest $request, LogisticUnitService $logisticUnitService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validated();

        if (blank($data['license_plate_number'] ?? null)) {
            $data['license_plate_number'] = $logisticUnitService->suggestLicensePlateNumber((int) $data['material_id']);
        }

        $logisticUnitService->create($data, (int) $request->user()->id);

        return back()->with('success', 'Pallet registrado.');
    }

    public function update(UpdateLogisticUnitRequest $request, InventoryLogisticUnit $logisticUnit, LogisticUnitService $logisticUnitService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $logisticUnitService->update($logisticUnit, $request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Pallet actualizado.');
    }

    public function destroy(Request $request, InventoryLogisticUnit $logisticUnit, LogisticUnitService $logisticUnitService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $logisticUnitService->close($logisticUnit, (int) $request->user()->id, $data['reason'] ?? null);

        return back()->with('success', 'Pallet eliminado.');
    }

    public function show(Request $request, InventoryLogisticUnit $logisticUnit): JsonResponse
    {
        $this->authorizeInventory($request);

        $logisticUnit->load([
            'material',
            'location',
            'unit',
            'ledgerEvents',
            'wasteRecords',
            'stockPositions.location',
        ]);

        return response()->json($logisticUnit);
    }

    public function showByCode(Request $request, string $code): JsonResponse
    {
        $this->authorizeInventory($request);

        $logisticUnit = InventoryLogisticUnit::query()
            ->where('license_plate_number', trim($code))
            ->with(['material:id,codigo,nombre', 'location:id,codigo,nombre', 'stockPositions.location:id,codigo,nombre'])
            ->firstOrFail();

        return response()->json([
            'id' => $logisticUnit->id,
            'license_plate_number' => $logisticUnit->license_plate_number,
            'dispatch_guide' => $logisticUnit->dispatch_guide,
            'material_id' => $logisticUnit->material_id,
            'material' => $logisticUnit->material,
            'location' => $logisticUnit->location,
            'available_quantity' => (float) $logisticUnit->available_quantity,
            'positions' => $logisticUnit->stockPositions
                ->where('quantity', '>', 0)
                ->values()
                ->map(fn (InventoryStockPosition $position) => [
                    'id' => $position->id,
                    'quantity' => (float) $position->quantity,
                    'location' => $position->location,
                ]),
        ]);
    }

    public function relocate(Request $request, InventoryLogisticUnit $logisticUnit, LogisticUnitService $logisticUnitService): RedirectResponse
    {
        $this->authorizeInventory($request);

        try {
            $data = $request->validate([
                'to_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
                'material_request_id' => ['nullable', 'integer', 'exists:inventory_material_requests,id'],
            ]);

            $logisticUnitService->relocate(
                $logisticUnit,
                (int) $data['to_location_id'],
                (int) $request->user()->id,
                $data['material_request_id'] ?? null
            );
        } catch (ValidationException $exception) {
            return back()->with('error', $this->validationMessage($exception));
        }

        return back()->with('success', 'Pallet trasladado correctamente.');
    }

    public function transferPosition(Request $request, InventoryStockPosition $stockPosition, MovementService $movementService): RedirectResponse
    {
        $this->authorizeInventory($request);

        try {
            $data = $request->validate([
                'to_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
                'quantity' => ['required', 'numeric', 'gt:0'],
                'material_request_id' => ['nullable', 'integer', 'exists:inventory_material_requests,id'],
            ]);

            $stockPosition->loadMissing('logisticUnit');
            $logisticUnit = $stockPosition->logisticUnit;
            $quantity = (float) $data['quantity'];
            $userId = (int) $request->user()->id;
            $destination = InventoryLocation::query()
                ->with('assignedUsers')
                ->findOrFail((int) $data['to_location_id']);

            if (! $logisticUnit) {
                throw ValidationException::withMessages([
                    'position' => 'La posición seleccionada no pertenece a un LPN.',
                ]);
            }

            if ($logisticUnit->status !== 'active') {
                throw ValidationException::withMessages([
                    'logistic_unit' => 'El LPN seleccionado no está disponible para traslado.',
                ]);
            }

            if ((int) $stockPosition->location_id === (int) $destination->id) {
                throw ValidationException::withMessages([
                    'to_location_id' => 'La ubicación destino debe ser distinta al origen.',
                ]);
            }

            if ($quantity > (float) $stockPosition->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'La posición seleccionada no tiene stock suficiente.',
                ]);
            }

            $transferType = InventoryMovementType::query()
                ->where('codigo', 'TRANSFERENCIA')
                ->first();

            if (! $transferType) {
                throw ValidationException::withMessages([
                    'movement_type' => 'No existe el tipo de movimiento TRANSFERENCIA.',
                ]);
            }

            $requestId = $data['material_request_id'] ?? null;

            $movement = DB::transaction(function () use ($movementService, $transferType, $stockPosition, $destination, $logisticUnit, $quantity, $userId, $requestId) {
                $movement = $movementService->create([
                    'movement_type_id' => $transferType->id,
                    'fecha_movimiento' => now()->format('Y-m-d H:i:s'),
                    'origin_location_id' => $stockPosition->location_id,
                    'destination_location_id' => $destination->id,
                    'material_request_id' => $requestId,
                    'motivo' => 'Traslado parcial de LPN',
                    'observacion' => "Traslado parcial de {$logisticUnit->license_plate_number} desde posición #{$stockPosition->id}.",
                    'metadata' => [
                        'workflow' => 'transfer_scan',
                        'source' => 'logistic_unit_partial_transfer',
                        'logistic_unit_codes' => [$logisticUnit->license_plate_number],
                        'stock_position_id' => $stockPosition->id,
                        'partial_transfer' => [
                            'position_id' => $stockPosition->id,
                            'quantity' => round($quantity, 4),
                            'origin_location_id' => $stockPosition->location_id,
                            'destination_location_id' => $destination->id,
                        ],
                    ],
                    'details' => [[
                        'material_id' => $stockPosition->material_id,
                        'position_id' => $stockPosition->id,
                        'cantidad' => $quantity,
                        'sentido' => 'salida',
                        'observacion' => 'Traslado parcial pendiente de recepción.',
                    ]],
                ], $userId, false);

                $movement->transferUnits()->create([
                    'logistic_unit_id' => $logisticUnit->id,
                    'material_id' => $stockPosition->material_id,
                    'origin_location_id' => $stockPosition->location_id,
                    'destination_location_id' => $destination->id,
                    'quantity' => $quantity,
                    'status' => 'pending',
                    'metadata' => [
                        'license_plate_number' => $logisticUnit->license_plate_number,
                        'requested_position_id' => $stockPosition->id,
                        'requested_quantity' => round($quantity, 4),
                        'partial_transfer' => [
                            'position_id' => $stockPosition->id,
                            'quantity' => round($quantity, 4),
                        ],
                    ],
                ]);

                return $movementService->apply($movement, $userId);
            });

            $movement->loadMissing(['destination.assignedUsers', 'transferUnits.logisticUnit']);
            $destination->assignedUsers
                ->filter(fn ($user) => $user->id !== $userId)
                ->each(fn ($user) => $user->notify(new InventoryTransferDispatchedNotification($movement)));
        } catch (ValidationException $exception) {
            return back()->with('error', $this->validationMessage($exception));
        }

        return back()->with('success', 'Traslado parcial registrado y dejado en tránsito para recepción.');
    }

    private function validationMessage(ValidationException $exception): string
    {
        return collect($exception->errors())
            ->flatten()
            ->filter()
            ->first() ?? $exception->getMessage();
    }
}
