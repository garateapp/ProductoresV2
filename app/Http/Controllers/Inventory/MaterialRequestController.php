<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Models\InventoryMaterial;
use App\Models\InventoryMaterialRequest;
use App\Models\InventoryMaterialRequestItem;
use App\Models\InventoryMovementType;
use App\Models\InventoryStockLocation;
use App\Models\InventoryStockPosition;
use App\Models\User;
use App\Notifications\MaterialRequestCreatedNotification;
use App\Services\Inventory\LedgerService;
use App\Services\Inventory\MovementService;
use App\Services\Inventory\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MaterialRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'estado' => (string) $request->input('estado', ''),
            'location_id' => (string) $request->input('location_id', ''),
            'material_id' => (string) $request->input('material_id', ''),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
        ];

        $requests = InventoryMaterialRequest::with(['creator:id,name', 'originLocation:id,nombre', 'destinationLocation:id,nombre', 'items.material:id,codigo,nombre'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($inner) use ($filters): void {
                    $inner->where('codigo', 'like', '%'.$filters['q'].'%')
                        ->orWhere('observacion', 'like', '%'.$filters['q'].'%');
                });
            })
            ->when($filters['estado'] !== '', fn ($query) => $query->where('estado', $filters['estado']))
            ->when($filters['location_id'] !== '', fn ($query) => $query->where('origin_location_id', $filters['location_id']))
            ->when($filters['material_id'] !== '', fn ($query) => $query->whereHas('items', fn ($q) => $q->where('material_id', $filters['material_id'])))
            ->when($filters['date_from'] !== '', fn ($query) => $query->whereDate('fecha_solicitud', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($query) => $query->whereDate('fecha_solicitud', '<=', $filters['date_to']))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $requests = $requests->through(function ($request) {
            $materialIds = $request->items->pluck('material_id')->filter()->unique()->values()->all();
            $originId = $request->origin_location_id;

            if (! empty($materialIds) && $originId) {
                $stockByMaterial = InventoryStockLocation::query()
                    ->where('location_id', $originId)
                    ->whereIn('material_id', $materialIds)
                    ->get(['material_id', 'stock_actual'])
                    ->keyBy('material_id');

                $positions = InventoryStockPosition::query()
                    ->where('location_id', $originId)
                    ->whereIn('material_id', $materialIds)
                    ->where('quantity', '>', 0)
                    ->with('logisticUnit:id,license_plate_number')
                    ->get(['id', 'material_id', 'location_id', 'quantity', 'logistic_unit_id'])
                    ->groupBy('material_id');

                $request->items->each(function ($item) use ($stockByMaterial, $positions) {
                    $stock = $stockByMaterial->get((int) $item->material_id);
                    $itemPositions = $positions->get((int) $item->material_id, collect());
                    $item->stock_actual = $stock ? (float) $stock->stock_actual : 0;
                    $item->stock_positions = $itemPositions->map(fn ($p) => [
                        'id' => $p->id,
                        'quantity' => (float) $p->quantity,
                        'lpn' => $p->logisticUnit?->license_plate_number,
                        'license_plate' => $p->logisticUnit?->license_plate_number,
                    ])->values()->all();
                });
            }

            return $request;
        });

        return Inertia::render('Inventory/MaterialRequests/Index', [
            'requests' => $requests,
            'filters' => $filters,
            'locations' => InventoryLocation::where('activo', true)->get(['id', 'nombre']),
            'materials' => InventoryMaterial::get(['id', 'codigo', 'nombre']),
            'statuses' => ['pendiente', 'aprobado', 'rechazado', 'completado'],
            'userAssignedLocationIds' => $request->user()->inventoryLocations()->pluck('inventory_location_id'),
        ]);
    }

    public function create(): Response
    {
        $locations = InventoryLocation::where('activo', true)->get(['id', 'nombre']);
        $materials = InventoryMaterial::get(['id', 'codigo', 'nombre']);
        $stocks = InventoryStockLocation::query()
            ->whereIn('location_id', $locations->pluck('id'))
            ->whereIn('material_id', $materials->pluck('id'))
            ->get(['location_id', 'material_id', 'stock_actual']);

        return Inertia::render('Inventory/MaterialRequests/Create', [
            'locations' => $locations,
            'materials' => $materials,
            'stocks' => $stocks,
        ]);
    }

    public function store(Request $request, LedgerService $ledgerService, StockService $stockService)
    {
        $request->validate([
            'origin_location_id' => 'required|exists:inventory_locations,id',
            'destination_location_id' => 'required|exists:inventory_locations,id',
            'fecha_requerida' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:inventory_materials,id',
            'items.*.cantidad' => 'required|numeric|min:0.0001',
        ]);

        $originId = (int) $request->origin_location_id;
        $errors = [];
        foreach ($request->items as $i => $item) {
            $stock = $stockService->getStock((int) $item['material_id'], $originId);
            $available = (float) ($stock->stock_actual ?? 0);
            $requested = (float) $item['cantidad'];
            if ($available < $requested) {
                $material = InventoryMaterial::find((int) $item['material_id']);
                $name = $material ? ($material->codigo . ' · ' . $material->nombre) : "ID #{$item['material_id']}";
                $errors["items.$i.cantidad"] = "Stock insuficiente para {$name}. Disponible: {$available}, Solicitado: {$requested}";
            }
        }
        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($request, $ledgerService) {
            $lastRequest = InventoryMaterialRequest::latest('id')->first();
            $nextNumber = $lastRequest ? $lastRequest->id + 1 : 1;
            $codigo = 'SOL-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $materialRequest = InventoryMaterialRequest::create([
                'codigo' => $codigo,
                'created_by' => auth()->id(),
                'origin_location_id' => $request->origin_location_id,
                'destination_location_id' => $request->destination_location_id,
                'fecha_requerida' => $request->fecha_requerida,
                'observacion' => $request->observacion,
            ]);

            foreach ($request->items as $item) {
                InventoryMaterialRequestItem::create([
                    'material_request_id' => $materialRequest->id,
                    'material_id' => $item['material_id'],
                    'cantidad_solicitada' => $item['cantidad'],
                    'notas' => $item['notas'] ?? null,
                ]);
            }

            // Notificar a usuarios encargados de la ubicación origen
            $materialRequest->loadMissing(['originLocation.assignedUsers']);
            $originUsers = $materialRequest->originLocation?->assignedUsers ?? collect();
            foreach ($originUsers as $user) {
                $user->notify(new MaterialRequestCreatedNotification($materialRequest));
            }

            // Registro en Ledger para trazabilidad inmutable
            $ledgerService->append([
                'event_type' => 'MATERIAL_REQUEST_CREATED',
                'actor_user_id' => auth()->id(),
                'actor_name_snapshot' => auth()->user()->name,
                'payload' => [
                    'request_id' => $materialRequest->id,
                    'codigo' => $codigo,
                    'origin_id' => $request->origin_location_id,
                    'destination_id' => $request->destination_location_id,
                    'items_count' => count($request->items),
                ]
            ]);

            return redirect()->route('inventory.material-requests.index')->with('success', 'Solicitud creada correctamente: ' . $codigo);
        });
    }

    public function updateStatus(InventoryMaterialRequest $materialRequest, Request $request, LedgerService $ledgerService)
    {
        $data = $request->validate([
            'estado' => 'required|in:pendiente,aprobado,rechazado,completado',
        ]);

        if (in_array($data['estado'], ['aprobado', 'rechazado'], true)) {
            $materialRequest->loadMissing('originLocation.assignedUsers');
            $assigned = $materialRequest->originLocation?->assignedUsers ?? collect();
            if ($assigned->isNotEmpty() && ! $assigned->pluck('id')->contains((int) $request->user()->id)) {
                return back()->with('error', 'Solo los encargados de la ubicación de origen pueden ' . ($data['estado'] === 'aprobado' ? 'aprobar' : 'rechazar') . ' solicitudes.');
            }
        }

        DB::transaction(function () use ($materialRequest, $data, $ledgerService) {
            $oldStatus = $materialRequest->estado;
            $materialRequest->estado = $data['estado'];
            $materialRequest->save();

            // Registro en Ledger
            $ledgerService->append([
                'event_type' => 'MATERIAL_REQUEST_STATUS_CHANGED',
                'actor_user_id' => auth()->id(),
                'actor_name_snapshot' => auth()->user()->name,
                'payload' => [
                    'request_id' => $materialRequest->id,
                    'codigo' => $materialRequest->codigo,
                    'old_status' => $oldStatus,
                    'new_status' => $data['estado'],
                ]
            ]);
        });

        return back()->with('success', 'Estado de la solicitud actualizado a ' . $data['estado']);
    }

    public function updateItemQuantity(InventoryMaterialRequest $materialRequest, InventoryMaterialRequestItem $item, Request $request, StockService $stockService)
    {
        if ($materialRequest->estado !== 'pendiente') {
            return back()->with('error', 'Solo se pueden editar cantidades en solicitudes pendientes.');
        }

        $materialRequest->loadMissing('originLocation.assignedUsers');
        $assigned = $materialRequest->originLocation?->assignedUsers ?? collect();
        if ($assigned->isNotEmpty() && ! $assigned->pluck('id')->contains((int) $request->user()->id)) {
            return back()->with('error', 'Solo los encargados de la ubicación de origen pueden modificar cantidades.');
        }

        $data = $request->validate([
            'cantidad' => 'required|numeric|min:0.0001',
            'motivo_cambio' => 'required|string|max:1000',
        ]);

        $newQuantity = (float) $data['cantidad'];
        $oldQuantity = (float) $item->cantidad_solicitada;

        if (abs($newQuantity - $oldQuantity) < 0.0001) {
            return back()->with('info', 'La cantidad ingresada es igual a la actual.');
        }

        $stock = $stockService->getStock((int) $item->material_id, (int) $materialRequest->origin_location_id);
        $available = (float) ($stock->stock_actual ?? 0);
        $material = $item->material;

        if ($newQuantity > $available) {
            $name = $material ? ($material->codigo . ' · ' . $material->nombre) : "ID #{$item->material_id}";
            return back()->with('error', "Stock insuficiente para {$name}. Disponible: {$available}, Solicitado: {$newQuantity}");
        }

        DB::transaction(function () use ($item, $materialRequest, $newQuantity, $oldQuantity, $data, $request, $material) {
            $item->cantidad_solicitada = $newQuantity;
            $item->save();

            $changelog = $materialRequest->metadata ?? [];
            $changelog[] = [
                'type' => 'quantity_change',
                'item_id' => $item->id,
                'material' => $material ? ($material->codigo . ' · ' . $material->nombre) : "ID #{$item->material_id}",
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'motivo' => $data['motivo_cambio'],
                'changed_by' => (int) $request->user()->id,
                'changed_by_name' => $request->user()->name,
                'changed_at' => now()->toDateTimeString(),
            ];
            $materialRequest->metadata = $changelog;
            $materialRequest->save();
        });

        return back()->with('success', 'Cantidad actualizada correctamente.');
    }

    public function generateTransfer(InventoryMaterialRequest $materialRequest, Request $request, MovementService $movementService, StockService $stockService)
    {
        if ($materialRequest->estado !== 'aprobado') {
            return back()->with('error', 'Solo se pueden generar traslados para solicitudes aprobadas.');
        }

        return DB::transaction(function () use ($materialRequest, $movementService, $stockService) {
            $materialRequest->load(['items.material', 'originLocation', 'destinationLocation']);

            $details = [];
            $hasPositionsTable = Schema::hasTable('inventory_stock_positions');

            foreach ($materialRequest->items as $item) {
                $remainingToSatisfy = (float) $item->cantidad_solicitada;

                if ($hasPositionsTable) {
                    // Buscar posiciones en origen para este material (FIFO)
                    $positions = InventoryStockPosition::query()
                        ->where('location_id', $materialRequest->origin_location_id)
                        ->where('material_id', $item->material_id)
                        ->where('quantity', '>', 0)
                        ->orderBy('created_at')
                        ->get();

                    foreach ($positions as $position) {
                        if ($remainingToSatisfy <= 0) {
                            break;
                        }

                        $take = min($remainingToSatisfy, (float) $position->quantity);
                        $details[] = [
                            'material_id' => $item->material_id,
                            'cantidad' => $take,
                            'sentido' => 'salida',
                            'position_id' => $position->id,
                        ];
                        $remainingToSatisfy -= $take;
                    }

                    if ($remainingToSatisfy > 0) {
                        throw ValidationException::withMessages([
                            'stock' => "Stock insuficiente en pallets para {$item->material->nombre} en {$materialRequest->originLocation->nombre}. Faltan {$remainingToSatisfy} unidades."
                        ]);
                    }
                } else {
                    // Si no hay tabla de posiciones, usamos stock general
                    $stock = $stockService->getStock($item->material_id, $materialRequest->origin_location_id);
                    if ($stock->stock_actual < $item->cantidad_solicitada) {
                        throw ValidationException::withMessages([
                            'stock' => "Stock insuficiente para {$item->material->nombre} en {$materialRequest->originLocation->nombre}. Disponible: {$stock->stock_actual}, Requerido: {$item->cantidad_solicitada}"
                        ]);
                    }

                    $details[] = [
                        'material_id' => $item->material_id,
                        'cantidad' => $item->cantidad_solicitada,
                        'sentido' => 'salida',
                    ];
                }
            }

            // Buscar tipo de movimiento TRANSFERENCIA
            $type = InventoryMovementType::where('codigo', 'TRANSFERENCIA')->firstOrFail();

            // Crear el movimiento
            $movement = $movementService->create([
                'movement_type_id' => $type->id,
                'fecha_movimiento' => now(),
                'origin_location_id' => $materialRequest->origin_location_id,
                'destination_location_id' => $materialRequest->destination_location_id,
                'material_request_id' => $materialRequest->id,
                'motivo' => 'Traslado generado desde solicitud ' . $materialRequest->codigo,
                'details' => $details,
            ], auth()->id(), true); // true para aplicar inmediatamente

            // Actualizar estado de la solicitud
            $materialRequest->estado = 'completado';
            $materialRequest->save();

            return redirect()->route('inventory.material-requests.index')->with('success', 'Traslado generado correctamente. Folio Movimiento: ' . $movement->folio);
        });
    }

    // Gestión de Encargados de Ubicación

    public function showUsers(InventoryLocation $location): Response
    {
        return Inertia::render('Inventory/Locations/Users', [
            'location' => $location,
            'assignedUsers' => $location->assignedUsers,
            'allUsers' => User::where('is_active', true)->whereNull('idprod')->get(['id', 'name']),
        ]);
    }

    public function syncUsers(InventoryLocation $location, Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $location->assignedUsers()->sync($request->user_ids);

        return back()->with('success', 'Usuarios asociados correctamente a la ubicación.');
    }
}
