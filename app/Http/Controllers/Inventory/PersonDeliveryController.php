<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryLocation;
use App\Models\InventoryMaterial;
use App\Models\InventoryMovementType;
use App\Models\InventoryPersonDelivery;
use App\Models\InventoryStockLocation;
use App\Models\InventoryStockPosition;
use App\Services\Inventory\MovementService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Browsershot\Browsershot;

class PersonDeliveryController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $deliveries = InventoryPersonDelivery::query()
            ->with([
                'creator:id,name',
                'originLocation:id,nombre',
                'movement:id,folio,estado',
                'items.material:id,codigo,nombre,unit_id',
                'items.material.unit:id,codigo',
            ])
            ->latest('delivered_at')
            ->paginate(15)
            ->through(fn (InventoryPersonDelivery $delivery) => $this->presentDelivery($delivery));

        return Inertia::render('Inventory/PersonDeliveries/Index', [
            'deliveries' => $deliveries,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeInventory($request);

        return Inertia::render('Inventory/PersonDeliveries/Create', [
            'locations' => InventoryLocation::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'materials' => InventoryMaterial::query()
                ->with('unit:id,codigo')
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'unit_id'])
                ->map(fn (InventoryMaterial $material) => [
                    'id' => $material->id,
                    'codigo' => $material->codigo,
                    'nombre' => $material->nombre,
                    'unit' => $material->unit?->codigo,
                ]),
        ]);
    }

    public function store(Request $request, MovementService $movementService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'origin_location_id' => ['required', 'exists:inventory_locations,id'],
            'person_name' => ['required', 'string', 'max:150'],
            'person_position' => ['required', 'string', 'max:150'],
            'delivered_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'signature_data_url' => [
                'required',
                'string',
                fn (string $attribute, mixed $value, Closure $fail) => str_starts_with((string) $value, 'data:image/png;base64,')
                    ? null
                    : $fail('La firma debe ser una imagen PNG generada desde el cuadro de firma.'),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'exists:inventory_materials,id'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ]);

        $items = $this->normalizeItems($data['items']);
        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Debes agregar al menos un material con cantidad mayor a cero.',
            ]);
        }

        $delivery = DB::transaction(function () use ($data, $items, $movementService, $request): InventoryPersonDelivery {
            $origin = InventoryLocation::query()->findOrFail((int) $data['origin_location_id']);
            $materialNames = InventoryMaterial::query()
                ->whereIn('id', $items->pluck('material_id')->all())
                ->pluck('nombre', 'id');

            $movementDetails = $this->buildMovementDetails($items, $origin, $materialNames);
            $code = 'ENT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

            $delivery = InventoryPersonDelivery::query()->create([
                'codigo' => $code,
                'created_by' => (int) $request->user()->id,
                'origin_location_id' => $origin->id,
                'person_name' => trim((string) $data['person_name']),
                'person_position' => trim((string) $data['person_position']),
                'delivered_at' => $data['delivered_at'] ?? now(),
                'signature_data_url' => $data['signature_data_url'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $delivery->items()->create([
                    'material_id' => $item['material_id'],
                    'cantidad' => $item['cantidad'],
                ]);
            }

            $consumeType = InventoryMovementType::query()->where('codigo', 'CONSUMO')->firstOrFail();
            $movement = $movementService->create([
                'movement_type_id' => $consumeType->id,
                'fecha_movimiento' => $delivery->delivered_at,
                'origin_location_id' => $origin->id,
                'referencia_tipo' => 'inventory_person_delivery',
                'referencia_id' => $delivery->id,
                'motivo' => 'Entrega de materiales a persona',
                'observacion' => $data['notes'] ?? null,
                'metadata' => [
                    'workflow' => 'person_delivery',
                    'person_delivery_id' => $delivery->id,
                    'person_name' => $delivery->person_name,
                    'person_position' => $delivery->person_position,
                    'signature_hash' => hash('sha256', $delivery->signature_data_url),
                ],
                'details' => $movementDetails,
            ], (int) $request->user()->id, true);

            $delivery->forceFill(['movement_id' => $movement->id])->save();

            return $delivery;
        });

        return redirect()
            ->route('inventory.person-deliveries.show', $delivery)
            ->with('success', 'Acta de entrega generada y stock descontado.');
    }

    public function show(Request $request, InventoryPersonDelivery $personDelivery): Response
    {
        $this->authorizeInventory($request);

        $personDelivery = $this->loadDeliveryForAct($personDelivery);

        return Inertia::render('Inventory/PersonDeliveries/Show', [
            'delivery' => $this->presentDelivery($personDelivery, true),
        ]);
    }

    public function pdf(Request $request, InventoryPersonDelivery $personDelivery)
    {
        $this->authorizeInventory($request);

        $personDelivery = $this->loadDeliveryForAct($personDelivery);

        $html = view('reports.inventory_person_delivery', [
            'delivery' => $personDelivery,
        ])->render();

        $pdf = Browsershot::html($html)
            ->format('A4')
            ->margins(12, 12, 12, 12)
            ->showBackground()
            ->pdf();

        $filename = 'Acta_Entrega_'.$personDelivery->codigo.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function loadDeliveryForAct(InventoryPersonDelivery $personDelivery): InventoryPersonDelivery
    {
        return $personDelivery->load([
            'creator:id,name',
            'originLocation:id,nombre,codigo',
            'movement:id,folio,estado,ledger_hash',
            'items.material:id,codigo,nombre,unit_id',
            'items.material.unit:id,codigo',
        ]);
    }

    private function normalizeItems(array $items): Collection
    {
        return collect($items)
            ->map(fn (array $item) => [
                'material_id' => (int) $item['material_id'],
                'cantidad' => round((float) $item['cantidad'], 4),
            ])
            ->filter(fn (array $item) => $item['material_id'] > 0 && $item['cantidad'] > 0)
            ->groupBy('material_id')
            ->map(fn (Collection $rows, int $materialId) => [
                'material_id' => $materialId,
                'cantidad' => round((float) $rows->sum('cantidad'), 4),
            ])
            ->values();
    }

    private function buildMovementDetails(Collection $items, InventoryLocation $origin, Collection $materialNames): array
    {
        $details = [];
        $hasPositionsTable = Schema::hasTable('inventory_stock_positions');

        foreach ($items as $item) {
            $materialId = (int) $item['material_id'];
            $quantity = (float) $item['cantidad'];
            $materialName = (string) ($materialNames->get($materialId) ?? 'material');

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
                            'items' => "Stock insuficiente en posiciones para {$materialName}. Faltan ".$this->formatQuantity($remaining).' unidades.',
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
                    'items' => "Stock insuficiente para {$materialName}. Disponible: ".$this->formatQuantity($available).'. Requerido: '.$this->formatQuantity($quantity).'.',
                ]);
            }

            $details[] = [
                'material_id' => $materialId,
                'cantidad' => $quantity,
                'sentido' => 'salida',
            ];
        }

        return $details;
    }

    private function presentDelivery(InventoryPersonDelivery $delivery, bool $includeSignature = false): array
    {
        return [
            'id' => $delivery->id,
            'codigo' => $delivery->codigo,
            'person_name' => $delivery->person_name,
            'person_position' => $delivery->person_position,
            'delivered_at' => optional($delivery->delivered_at)->toISOString(),
            'notes' => $delivery->notes,
            'signature_data_url' => $includeSignature ? $delivery->signature_data_url : null,
            'creator' => $delivery->creator ? [
                'id' => $delivery->creator->id,
                'name' => $delivery->creator->name,
            ] : null,
            'origin_location' => $delivery->originLocation ? [
                'id' => $delivery->originLocation->id,
                'codigo' => $delivery->originLocation->codigo ?? null,
                'nombre' => $delivery->originLocation->nombre,
            ] : null,
            'movement' => $delivery->movement ? [
                'id' => $delivery->movement->id,
                'folio' => $delivery->movement->folio,
                'estado' => $delivery->movement->estado,
                'ledger_hash' => $delivery->movement->ledger_hash ?? null,
            ] : null,
            'items' => $delivery->items->map(fn ($item) => [
                'id' => $item->id,
                'cantidad' => (float) $item->cantidad,
                'material' => $item->material ? [
                    'id' => $item->material->id,
                    'codigo' => $item->material->codigo,
                    'nombre' => $item->material->nombre,
                    'unit' => $item->material->unit?->codigo,
                ] : null,
            ])->values(),
        ];
    }

    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 4, ',', '.');
    }
}
