<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMaterial;
use App\Models\InventoryMovementType;
use App\Models\InventoryReturn;
use App\Models\InventoryReturnItem;
use App\Models\InventoryStockLocation;
use App\Models\InventoryStockPosition;
use App\Notifications\InventoryTransferDispatchedNotification;
use App\Notifications\ReturnCreatedNotification;
use App\Services\Inventory\LedgerService;
use App\Services\Inventory\LogisticUnitService;
use App\Services\Inventory\MovementService;
use App\Services\Inventory\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
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

        $returns = InventoryReturn::with(['creator:id,name', 'originLocation:id,nombre', 'destinationLocation:id,nombre', 'items.material:id,codigo,nombre', 'items.position.logisticUnit:id,license_plate_number'])
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

        $returns = $returns->through(function ($return) {
            $materialIds = $return->items->pluck('material_id')->filter()->unique()->values()->all();
            $originId = $return->origin_location_id;

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

                $return->items->each(function ($item) use ($stockByMaterial, $positions) {
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

            return $return;
        });

        return Inertia::render('Inventory/Returns/Index', [
            'returns' => $returns,
            'filters' => $filters,
            'locations' => InventoryLocation::where('activo', true)->get(['id', 'nombre']),
            'materials' => InventoryMaterial::get(['id', 'codigo', 'nombre']),
            'statuses' => ['pendiente', 'aprobado', 'rechazado', 'completado'],
            'userAssignedLocationIds' => $request->user()->inventoryLocations()->pluck('inventory_location_id'),
        ]);
    }

    public function create(Request $request): Response
    {
        $userLocationIds = $request->user()->inventoryLocations()->pluck('inventory_location_id');

        $userLocations = InventoryLocation::whereIn('id', $userLocationIds)
            ->where('activo', true)
            ->get(['id', 'nombre']);

        $materials = InventoryMaterial::get(['id', 'codigo', 'nombre']);

        $positionsByLocation = InventoryStockPosition::query()
            ->whereIn('location_id', $userLocationIds)
            ->where('quantity', '>', 0)
            ->with(['logisticUnit:id,license_plate_number', 'material:id,codigo,nombre'])
            ->get(['id', 'material_id', 'location_id', 'quantity', 'logistic_unit_id'])
            ->groupBy('location_id')
            ->map(function ($positions) {
                return $positions->map(fn ($p) => [
                    'id' => $p->id,
                    'material_id' => $p->material_id,
                    'material' => $p->material ? ['codigo' => $p->material->codigo, 'nombre' => $p->material->nombre] : null,
                    'quantity' => (float) $p->quantity,
                    'lpn' => $p->logisticUnit?->license_plate_number,
                ]);
            })->all();

        $allLocations = InventoryLocation::where('activo', true)->get(['id', 'nombre']);

        return Inertia::render('Inventory/Returns/Create', [
            'userLocations' => $userLocations,
            'locations' => $allLocations,
            'materials' => $materials,
            'positionsByLocation' => $positionsByLocation,
        ]);
    }

    public function store(Request $request, LedgerService $ledgerService, StockService $stockService)
    {
        $data = $request->validate([
            'origin_location_id' => 'required|exists:inventory_locations,id',
            'destination_location_id' => 'required|exists:inventory_locations,id',
            'fecha_requerida' => 'nullable|date',
            'observacion' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:inventory_materials,id',
            'items.*.cantidad_devuelta' => 'required|numeric|min:0.0001',
            'items.*.position_id' => 'nullable|exists:inventory_stock_positions,id',
            'items.*.notas' => 'nullable|string|max:1000',
        ]);

        // Validar que el usuario es encargado de la ubicación origen
        $userLocationIds = $request->user()->inventoryLocations()->pluck('inventory_location_id')->toArray();
        if (! in_array((int) $data['origin_location_id'], $userLocationIds, true)) {
            return back()->with('error', 'No puedes crear devoluciones desde una ubicación donde no eres encargado.');
        }

        return DB::transaction(function () use ($data, $request, $ledgerService) {
            // Validar stock por cada item
            $hasPositionsTable = Schema::hasTable('inventory_stock_positions');

            foreach ($data['items'] as $itemData) {
                $materialId = (int) $itemData['material_id'];
                $cantidad = (float) $itemData['cantidad_devuelta'];
                $positionId = isset($itemData['position_id']) ? (int) $itemData['position_id'] : null;

                if ($hasPositionsTable && $positionId) {
                    $position = InventoryStockPosition::query()
                        ->where('id', $positionId)
                        ->where('location_id', $data['origin_location_id'])
                        ->where('material_id', $materialId)
                        ->first();

                    if (! $position) {
                        throw ValidationException::withMessages([
                            'items' => "La posición seleccionada para el material no existe o no pertenece a la ubicación origen.",
                        ]);
                    }

                    if ((float) $position->quantity < $cantidad) {
                        throw ValidationException::withMessages([
                            'items' => "Stock insuficiente en la posición {$position->id}. Disponible: {$position->quantity}, Requerido: {$cantidad}",
                        ]);
                    }
                } elseif ($hasPositionsTable) {
                    // Sin posición específica: sumar stock de todas las posiciones
                    $totalInPositions = (float) InventoryStockPosition::query()
                        ->where('location_id', $data['origin_location_id'])
                        ->where('material_id', $materialId)
                        ->where('quantity', '>', 0)
                        ->sum('quantity');

                    if ($totalInPositions < $cantidad) {
                        throw ValidationException::withMessages([
                            'items' => "Stock insuficiente en pallets para el material. Disponible: {$totalInPositions}, Requerido: {$cantidad}",
                        ]);
                    }
                } else {
                    $stock = $stockService->getStock($materialId, $data['origin_location_id']);
                    if ($stock->stock_actual < $cantidad) {
                        throw ValidationException::withMessages([
                            'items' => "Stock insuficiente para el material. Disponible: {$stock->stock_actual}, Requerido: {$cantidad}",
                        ]);
                    }
                }
            }

            // Generar código
            $lastReturn = InventoryReturn::latest('id')->first();
            $nextId = $lastReturn ? $lastReturn->id + 1 : 1;
            $codigo = 'DEV-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);

            $return = InventoryReturn::create([
                'codigo' => $codigo,
                'created_by' => $request->user()->id,
                'origin_location_id' => $data['origin_location_id'],
                'destination_location_id' => $data['destination_location_id'],
                'estado' => 'pendiente',
                'observacion' => $data['observacion'] ?? null,
                'fecha_solicitud' => now(),
                'fecha_requerida' => $data['fecha_requerida'] ?? null,
            ]);

            foreach ($data['items'] as $itemData) {
                $return->items()->create([
                    'material_id' => $itemData['material_id'],
                    'position_id' => $itemData['position_id'] ?? null,
                    'cantidad_devuelta' => $itemData['cantidad_devuelta'],
                    'notas' => $itemData['notas'] ?? null,
                ]);
            }

            // Notificar a encargados de destino
            $return->load('destinationLocation.assignedUsers');
            $destinationUsers = $return->destinationLocation?->assignedUsers ?? collect();
            if ($destinationUsers->isNotEmpty()) {
                foreach ($destinationUsers as $user) {
                    $user->notify(new ReturnCreatedNotification($return));
                }
            }

            // Ledger
            $ledgerService->append([
                'event_type' => 'RETURN_CREATED',
                'actor_user_id' => $request->user()->id,
                'actor_name_snapshot' => $request->user()->name,
                'payload' => [
                    'return_id' => $return->id,
                    'codigo' => $codigo,
                    'origin_location_id' => $data['origin_location_id'],
                    'destination_location_id' => $data['destination_location_id'],
                    'items_count' => count($data['items']),
                ],
            ]);

            return redirect()->route('inventory.returns.index')->with('success', 'Devolución creada correctamente. Código: ' . $codigo);
        });
    }

    public function updateStatus(InventoryReturn $return, Request $request, LedgerService $ledgerService)
    {
        $data = $request->validate([
            'estado' => 'required|in:pendiente,aprobado,rechazado,completado',
        ]);

        // Solo encargados de DESTINO pueden aprobar/rechazar
        if (in_array($data['estado'], ['aprobado', 'rechazado'], true)) {
            $return->loadMissing('destinationLocation.assignedUsers');
            $assigned = $return->destinationLocation?->assignedUsers ?? collect();
            if ($assigned->isNotEmpty() && ! $assigned->pluck('id')->contains((int) $request->user()->id)) {
                return back()->with('error', 'Solo los encargados de la ubicación de destino pueden ' . ($data['estado'] === 'aprobado' ? 'aprobar' : 'rechazar') . ' devoluciones.');
            }
        }

        DB::transaction(function () use ($return, $data, $ledgerService) {
            $oldStatus = $return->estado;
            $return->estado = $data['estado'];
            $return->save();

            $ledgerService->append([
                'event_type' => 'RETURN_STATUS_CHANGED',
                'actor_user_id' => auth()->id(),
                'actor_name_snapshot' => auth()->user()->name,
                'payload' => [
                    'return_id' => $return->id,
                    'codigo' => $return->codigo,
                    'old_status' => $oldStatus,
                    'new_status' => $data['estado'],
                ],
            ]);
        });

        return back()->with('success', 'Estado de la devolución actualizado a ' . $data['estado']);
    }

    public function generateTransfer(InventoryReturn $return, Request $request, MovementService $movementService, LogisticUnitService $logisticUnitService)
    {
        if ($return->estado !== 'aprobado') {
            return back()->with('error', 'Solo se pueden generar traslados para devoluciones aprobadas.');
        }

        try {
            $return->load(['items.material', 'items.position.logisticUnit', 'originLocation', 'destinationLocation']);

            $userId = (int) $request->user()->id;

            $movement = DB::transaction(function () use ($return, $movementService, $userId, $request) {
                $type = InventoryMovementType::where('codigo', 'DEVOLUCION')->firstOrFail();

                $hasPositionsTable = Schema::hasTable('inventory_stock_positions');
                $details = [];
                $tuData = [];

                foreach ($return->items as $item) {
                    $remaining = (float) $item->cantidad_devuelta;

                    if ($hasPositionsTable && $item->position_id) {
                        $position = $item->position;

                        if (! $position) {
                            throw ValidationException::withMessages([
                                'stock' => "La posición seleccionada para {$item->material->nombre} ya no existe.",
                            ]);
                        }

                        $take = min($remaining, (float) $position->quantity);
                        if ($take <= 0) {
                            throw ValidationException::withMessages([
                                'stock' => "La posición LPN {$position->logisticUnit?->license_plate_number} para {$item->material->nombre} no tiene stock disponible.",
                            ]);
                        }

                        $details[] = [
                            'material_id' => $item->material_id,
                            'position_id' => $position->id,
                            'cantidad' => $take,
                            'sentido' => 'salida',
                            'observacion' => 'Devolución pendiente de recepción.',
                        ];

                        if ($position->logisticUnit) {
                            $tuData[] = [
                                'logistic_unit_id' => $position->logisticUnit->id,
                                'logisticUnit' => $position->logisticUnit,
                                'material_id' => $item->material_id,
                                'quantity' => $take,
                                'position_id' => $position->id,
                            ];
                        }
                    } elseif ($hasPositionsTable) {
                        // Sin posición específica: FIFO desde posiciones
                        $positions = InventoryStockPosition::query()
                            ->where('location_id', $return->origin_location_id)
                            ->where('material_id', $item->material_id)
                            ->where('quantity', '>', 0)
                            ->orderBy('created_at')
                            ->get();

                        foreach ($positions as $position) {
                            if ($remaining <= 0) break;
                            $take = min($remaining, (float) $position->quantity);
                            $details[] = [
                                'material_id' => $item->material_id,
                                'position_id' => $position->id,
                                'cantidad' => $take,
                                'sentido' => 'salida',
                                'observacion' => 'Devolución pendiente de recepción.',
                            ];
                            if ($position->logisticUnit) {
                                $tuData[] = [
                                    'logistic_unit_id' => $position->logisticUnit->id,
                                    'logisticUnit' => $position->logisticUnit,
                                    'material_id' => $item->material_id,
                                    'quantity' => $take,
                                    'position_id' => $position->id,
                                ];
                            }
                            $remaining -= $take;
                        }

                        if ($remaining > 0) {
                            throw ValidationException::withMessages([
                                'stock' => "Stock insuficiente en pallets para {$item->material->nombre} en {$return->originLocation->nombre}. Faltan {$remaining} unidades.",
                            ]);
                        }
                    } else {
                        throw ValidationException::withMessages([
                            'stock' => "La devolución requiere tabla de posiciones. No es posible procesar sin LPN.",
                        ]);
                    }
                }

                $movement = $movementService->create([
                    'movement_type_id' => $type->id,
                    'fecha_movimiento' => now()->format('Y-m-d H:i:s'),
                    'origin_location_id' => $return->origin_location_id,
                    'destination_location_id' => $return->destination_location_id,
                    'return_id' => $return->id,
                    'motivo' => 'Devolución generada desde ' . $return->codigo,
                    'observacion' => "Devolución desde {$return->originLocation?->nombre} hacia {$return->destinationLocation?->nombre}.",
                    'metadata' => [
                        'workflow' => 'transfer_scan',
                        'source' => 'return',
                        'return_codigo' => $return->codigo,
                        'logistic_unit_codes' => collect($tuData)->pluck('logisticUnit.license_plate_number')->unique()->values()->all(),
                    ],
                    'details' => $details,
                ], $userId, false);

                // Crear TUs por cada posición/LPN
                foreach ($tuData as $tu) {
                    $movement->transferUnits()->create([
                        'logistic_unit_id' => $tu['logistic_unit_id'],
                        'material_id' => $tu['material_id'],
                        'origin_location_id' => $return->origin_location_id,
                        'destination_location_id' => $return->destination_location_id,
                        'quantity' => $tu['quantity'],
                        'status' => 'pending',
                        'metadata' => [
                            'license_plate_number' => $tu['logisticUnit']->license_plate_number,
                            'requested_position_id' => $tu['position_id'],
                            'requested_quantity' => round($tu['quantity'], 4),
                            'partial_transfer' => [
                                'position_id' => $tu['position_id'],
                                'quantity' => round($tu['quantity'], 4),
                            ],
                        ],
                    ]);
                }

                return $movementService->apply($movement, $userId);
            });

            $movement->loadMissing(['destination.assignedUsers', 'transferUnits.logisticUnit']);
            $destination = $return->destinationLocation;
            if ($destination) {
                $destination->assignedUsers
                    ->filter(fn ($user) => $user->id !== $userId)
                    ->each(fn ($user) => $user->notify(new InventoryTransferDispatchedNotification($movement)));
            }

            $return->estado = 'completado';
            $return->save();

            return redirect()->route('inventory.returns.index')->with('success', 'Devolución procesada correctamente. Folio Movimiento: ' . $movement->folio);
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        }
    }
}
