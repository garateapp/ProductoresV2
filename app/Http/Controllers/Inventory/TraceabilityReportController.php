<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryLedgerEvent;
use App\Models\InventoryMaterial;
use App\Models\InventoryMaterialRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TraceabilityReportController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'estado' => (string) $request->input('estado', ''),
            'material_id' => (string) $request->input('material_id', ''),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
        ];

        $requests = InventoryMaterialRequest::query()
            ->with([
                'creator:id,name',
                'originLocation:id,codigo,nombre',
                'destinationLocation:id,codigo,nombre',
                'items.material:id,codigo,nombre',
                'movements' => fn ($query) => $query
                    ->with([
                        'type:id,codigo,nombre',
                        'origin:id,codigo,nombre',
                        'destination:id,codigo,nombre',
                        'creator:id,name',
                        'confirmer:id,name',
                        'details.material:id,codigo,nombre',
                        'details.allocations.logisticUnit:id,license_plate_number,status,available_quantity,current_location_id,spatial_prefix,spatial_column,spatial_row',
                        'details.allocations.logisticUnit.location:id,codigo,nombre',
                        'transferUnits.logisticUnit:id,license_plate_number,status,available_quantity,current_location_id,spatial_prefix,spatial_column,spatial_row',
                        'transferUnits.logisticUnit.location:id,codigo,nombre',
                        'ledgerEvents',
                    ])
                    ->orderBy('fecha_movimiento'),
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($inner) use ($filters): void {
                    $inner->where('codigo', 'like', '%'.$filters['q'].'%')
                        ->orWhere('observacion', 'like', '%'.$filters['q'].'%');
                });
            })
            ->when($filters['estado'] !== '', fn ($query) => $query->where('estado', $filters['estado']))
            ->when($filters['material_id'] !== '', fn ($query) => $query->whereHas('items', fn ($item) => $item->where('material_id', $filters['material_id'])))
            ->when($filters['date_from'] !== '', fn ($query) => $query->whereDate('fecha_solicitud', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($query) => $query->whereDate('fecha_solicitud', '<=', $filters['date_to']))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $requestCollection = $requests->getCollection();
        $logisticUnitIds = $requestCollection
            ->flatMap(fn (InventoryMaterialRequest $materialRequest) => $this->linkedLogisticUnitIds($materialRequest))
            ->unique()
            ->values();

        $finalEvents = InventoryLedgerEvent::query()
            ->with([
                'material:id,codigo,nombre',
                'location:id,codigo,nombre',
                'logisticUnit:id,license_plate_number',
                'movement.type:id,codigo,nombre',
                'movement.personDelivery:id,movement_id,codigo,person_name,person_position',
            ])
            ->whereIn('logistic_unit_id', $logisticUnitIds)
            ->whereIn('event_type', ['CONSUME_OUT', 'WASTE_OUT', 'ADJUST_NEG', 'PRODUCTION_INTERMEDIATE_OUT'])
            ->orderBy('occurred_at')
            ->get()
            ->groupBy('logistic_unit_id');

        $requests->setCollection(
            $requestCollection->map(fn (InventoryMaterialRequest $materialRequest) => $this->mapRequest($materialRequest, $finalEvents))
        );

        return Inertia::render('Inventory/TraceabilityReports/Index', [
            'filters' => $filters,
            'requests' => $requests,
            'statuses' => ['pendiente', 'aprobado', 'rechazado', 'completado'],
            'materials' => InventoryMaterial::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
        ]);
    }

    private function mapRequest(InventoryMaterialRequest $materialRequest, $finalEvents): array
    {
        $linkedLogisticUnitIds = $this->linkedLogisticUnitIds($materialRequest);
        $requestFinalEvents = $linkedLogisticUnitIds
            ->flatMap(fn (int $id) => $finalEvents->get($id, collect()))
            ->filter(fn (InventoryLedgerEvent $event) => ! $materialRequest->fecha_solicitud || $event->occurred_at?->gte($materialRequest->fecha_solicitud))
            ->values();

        $requestedTotal = (float) $materialRequest->items->sum('cantidad_solicitada');
        $transferredTotal = $this->sumMovementDetails($materialRequest, ['TRANSFERENCIA', 'DEVOLUCION']);
        $receivedTotal = (float) $materialRequest->movements
            ->flatMap->ledgerEvents
            ->where('event_type', 'TRANSFER_IN')
            ->sum(fn (InventoryLedgerEvent $event) => abs((float) $event->signed_quantity));
        $consumedTotal = (float) $requestFinalEvents->sum(fn (InventoryLedgerEvent $event) => abs((float) $event->signed_quantity));

        return [
            'id' => $materialRequest->id,
            'codigo' => $materialRequest->codigo,
            'estado' => $materialRequest->estado,
            'fecha_solicitud' => optional($materialRequest->fecha_solicitud)->format('Y-m-d H:i'),
            'fecha_requerida' => optional($materialRequest->fecha_requerida)->format('Y-m-d'),
            'creator' => $materialRequest->creator?->name,
            'origin' => $this->locationLabel($materialRequest->originLocation),
            'destination' => $this->locationLabel($materialRequest->destinationLocation),
            'observacion' => $materialRequest->observacion,
            'summary' => [
                'requested' => round($requestedTotal, 4),
                'transferred' => round($transferredTotal, 4),
                'received' => round($receivedTotal, 4),
                'consumed' => round($consumedTotal, 4),
                'pending_consumption' => round(max($requestedTotal - $consumedTotal, 0), 4),
                'progress' => $requestedTotal > 0 ? round(min(($consumedTotal / $requestedTotal) * 100, 100), 1) : 0,
            ],
            'items' => $this->mapItems($materialRequest, $requestFinalEvents),
            'pallets' => $this->mapPallets($materialRequest, $requestFinalEvents),
            'movements' => $this->mapMovements($materialRequest),
            'timeline' => $this->mapTimeline($materialRequest, $requestFinalEvents),
        ];
    }

    private function mapItems(InventoryMaterialRequest $materialRequest, $finalEvents): array
    {
        return $materialRequest->items->map(function ($item) use ($materialRequest, $finalEvents): array {
            $transferred = (float) $materialRequest->movements
                ->flatMap->details
                ->where('material_id', $item->material_id)
                ->sum('cantidad');
            $consumed = (float) $finalEvents
                ->where('material_id', $item->material_id)
                ->sum(fn (InventoryLedgerEvent $event) => abs((float) $event->signed_quantity));

            return [
                'id' => $item->id,
                'material' => $this->materialLabel($item->material),
                'requested' => round((float) $item->cantidad_solicitada, 4),
                'transferred' => round($transferred, 4),
                'consumed' => round($consumed, 4),
                'pending' => round(max((float) $item->cantidad_solicitada - $consumed, 0), 4),
            ];
        })->values()->all();
    }

    private function mapPallets(InventoryMaterialRequest $materialRequest, $finalEvents): array
    {
        $allocations = $materialRequest->movements->flatMap->details->flatMap->allocations;
        $transferUnits = $materialRequest->movements->flatMap->transferUnits;

        return $allocations
            ->pluck('logisticUnit')
            ->merge($transferUnits->pluck('logisticUnit'))
            ->filter()
            ->unique('id')
            ->map(function ($unit) use ($allocations, $transferUnits, $finalEvents): array {
                $linkedQuantity = (float) $allocations
                    ->where('logistic_unit_id', $unit->id)
                    ->sum('allocated_quantity');

                if ($linkedQuantity <= 0) {
                    $linkedQuantity = (float) $transferUnits
                        ->where('logistic_unit_id', $unit->id)
                        ->sum('quantity');
                }

                $events = $finalEvents
                    ->where('logistic_unit_id', $unit->id)
                    ->values();

                return [
                    'id' => $unit->id,
                    'lpn' => $unit->license_plate_number,
                    'status' => $unit->status,
                    'location' => $this->locationLabel($unit->location),
                    'spatial_position' => $this->spatialPositionLabel($unit),
                    'linked_quantity' => round($linkedQuantity, 4),
                    'consumed_quantity' => round((float) $events->sum(fn (InventoryLedgerEvent $event) => abs((float) $event->signed_quantity)), 4),
                    'available_quantity' => round((float) $unit->available_quantity, 4),
                    'last_event' => optional($events->last()?->occurred_at)->format('Y-m-d H:i'),
                ];
            })
            ->values()
            ->all();
    }

    private function mapMovements(InventoryMaterialRequest $materialRequest): array
    {
        return $materialRequest->movements->map(fn ($movement): array => [
            'id' => $movement->id,
            'folio' => $movement->folio,
            'type' => $movement->type?->nombre,
            'type_code' => $movement->type?->codigo,
            'status' => $movement->estado,
            'date' => optional($movement->fecha_movimiento)->format('Y-m-d H:i'),
            'route' => trim(($this->locationLabel($movement->origin) ?: '-').' -> '.($this->locationLabel($movement->destination) ?: '-')),
            'created_by' => $movement->creator?->name,
            'confirmed_by' => $movement->confirmer?->name,
            'quantity' => round((float) $movement->details->sum('cantidad'), 4),
            'pallet_count' => $movement->transferUnits->count() ?: $movement->details->flatMap->allocations->pluck('logistic_unit_id')->filter()->unique()->count(),
        ])->values()->all();
    }

    private function mapTimeline(InventoryMaterialRequest $materialRequest, $finalEvents): array
    {
        $events = collect([
            [
                'date' => optional($materialRequest->fecha_solicitud)->format('Y-m-d H:i'),
                'type' => 'Solicitud',
                'title' => "Solicitud {$materialRequest->codigo}",
                'detail' => "{$this->locationLabel($materialRequest->originLocation)} -> {$this->locationLabel($materialRequest->destinationLocation)}",
                'quantity' => round((float) $materialRequest->items->sum('cantidad_solicitada'), 4),
                'actor' => $materialRequest->creator?->name,
            ],
        ]);

        foreach ($materialRequest->movements as $movement) {
            $events->push([
                'date' => optional($movement->fecha_movimiento)->format('Y-m-d H:i'),
                'type' => $movement->type?->codigo ?? 'Movimiento',
                'title' => $movement->folio,
                'detail' => "{$movement->estado} · {$this->locationLabel($movement->origin)} -> {$this->locationLabel($movement->destination)}",
                'quantity' => round((float) $movement->details->sum('cantidad'), 4),
                'actor' => $movement->creator?->name,
            ]);
        }

        foreach ($finalEvents as $event) {
            $delivery = $event->movement?->personDelivery;
            $events->push([
                'date' => optional($event->occurred_at)->format('Y-m-d H:i'),
                'type' => $event->event_type,
                'title' => $event->movement?->folio ?? $event->event_type,
                'detail' => trim(($this->materialLabel($event->material) ?: '-').' · '.($event->logisticUnit?->license_plate_number ?: 'Sin LPN').' · '.($delivery ? "{$delivery->codigo} {$delivery->person_name}" : ($event->movement?->type?->nombre ?? ''))),
                'quantity' => round(abs((float) $event->signed_quantity), 4),
                'actor' => $event->actor_name_snapshot ?: $event->movement?->creator?->name,
            ]);
        }

        return $events
            ->sortBy('date')
            ->values()
            ->all();
    }

    private function linkedLogisticUnitIds(InventoryMaterialRequest $materialRequest)
    {
        return $materialRequest->movements
            ->flatMap(fn ($movement) => $movement->transferUnits->pluck('logistic_unit_id')
                ->merge($movement->details->flatMap->allocations->pluck('logistic_unit_id')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function sumMovementDetails(InventoryMaterialRequest $materialRequest, array $typeCodes): float
    {
        return (float) $materialRequest->movements
            ->filter(fn ($movement) => in_array((string) $movement->type?->codigo, $typeCodes, true))
            ->flatMap->details
            ->sum('cantidad');
    }

    private function materialLabel($material): ?string
    {
        return $material ? trim(($material->codigo ?? '').' · '.($material->nombre ?? '')) : null;
    }

    private function locationLabel($location): ?string
    {
        return $location ? trim(($location->codigo ? $location->codigo.' · ' : '').$location->nombre) : null;
    }

    private function spatialPositionLabel($unit): ?string
    {
        $parts = array_filter([
            $unit->spatial_prefix ? "Prefijo {$unit->spatial_prefix}" : null,
            $unit->spatial_column ? "Columna {$unit->spatial_column}" : null,
            $unit->spatial_row ? "Fila {$unit->spatial_row}" : null,
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }
}
