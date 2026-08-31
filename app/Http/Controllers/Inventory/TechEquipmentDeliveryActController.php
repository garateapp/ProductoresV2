<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryTechEquipment;
use App\Models\InventoryTechEquipmentDeliveryAct;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Browsershot\Browsershot;

class TechEquipmentDeliveryActController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $acts = InventoryTechEquipmentDeliveryAct::query()
            ->with(['creator:id,name', 'items.equipment:id,marca,fecha,numero_serie,descripcion'])
            ->latest('delivered_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Inventory/TechEquipmentDeliveries/Index', [
            'acts' => $acts->through(fn (InventoryTechEquipmentDeliveryAct $act) => $this->presentAct($act)),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeInventory($request);

        return Inertia::render('Inventory/TechEquipmentDeliveries/Create', [
            'equipment' => InventoryTechEquipment::query()
                ->where('activo', true)
                ->orderBy('marca')
                ->orderBy('numero_serie')
                ->get(['id', 'marca', 'fecha', 'numero_serie', 'descripcion']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'person_name' => ['required', 'string', 'max:150'],
            'person_rut' => ['required', 'string', 'max:20'],
            'departamento' => ['nullable', 'string', 'max:150'],
            'cargo' => ['nullable', 'string', 'max:150'],
            'condicion' => ['required', 'in:nuevo,usado'],
            'delivered_at' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
            'signature_data_url' => [
                'required',
                'string',
                fn (string $attribute, mixed $value, Closure $fail) => str_starts_with((string) $value, 'data:image/png;base64,')
                    ? null
                    : $fail('La firma debe ser una imagen PNG generada desde el cuadro de firma.'),
            ],
            'equipment_ids' => ['required', 'array', 'min:1'],
            'equipment_ids.*' => ['integer', 'exists:inventory_tech_equipment,id'],
        ]);

        $data['equipment_ids'] = array_values(array_unique(array_map('intval', $data['equipment_ids'] ?? [])));

        if (empty($data['equipment_ids'])) {
            throw ValidationException::withMessages([
                'equipment_ids' => 'Debes asignar al menos un equipo.',
            ]);
        }

        $act = DB::transaction(function () use ($data, $request): InventoryTechEquipmentDeliveryAct {
            $code = 'EQ-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

            $act = InventoryTechEquipmentDeliveryAct::query()->create([
                'codigo' => $code,
                'created_by' => (int) $request->user()->id,
                'person_name' => $data['person_name'],
                'person_rut' => $data['person_rut'],
                'departamento' => $data['departamento'] ?? null,
                'cargo' => $data['cargo'] ?? null,
                'condicion' => $data['condicion'],
                'delivered_at' => $data['delivered_at'] ?? now(),
                'signature_data_url' => $data['signature_data_url'],
                'observations' => $data['observations'] ?? null,
            ]);

            foreach ($data['equipment_ids'] as $equipmentId) {
                $act->items()->create(['equipment_id' => $equipmentId]);
            }

            return $act;
        });

        return redirect()
            ->route('inventory.tech-equipment-deliveries.show', $act)
            ->with('success', 'Acta de entrega de equipos tecnológicos generada.');
    }

    public function show(Request $request, InventoryTechEquipmentDeliveryAct $deliveryAct): Response
    {
        $this->authorizeInventory($request);

        $deliveryAct->load(['creator:id,name', 'items.equipment:id,marca,fecha,numero_serie,descripcion']);

        return Inertia::render('Inventory/TechEquipmentDeliveries/Show', [
            'act' => $this->presentAct($deliveryAct, true),
        ]);
    }

    public function pdf(Request $request, InventoryTechEquipmentDeliveryAct $deliveryAct)
    {
        $this->authorizeInventory($request);

        $deliveryAct->load(['creator:id,name', 'items.equipment:id,marca,fecha,numero_serie,descripcion']);

        $html = view('reports.inventory_tech_equipment_delivery', [
            'act' => $deliveryAct,
        ])->render();

        $pdf = Browsershot::html($html)
            ->format('A4')
            ->margins(12, 12, 12, 12)
            ->showBackground()
            ->pdf();

        $filename = 'Acta_Entrega_Equipos_'.$deliveryAct->codigo.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function returnAct(Request $request, InventoryTechEquipmentDeliveryAct $deliveryAct): RedirectResponse
    {
        $this->authorizeInventory($request);

        if ($deliveryAct->returned_at) {
            throw ValidationException::withMessages([
                'return_observations' => 'Este acta ya fue marcada como devuelta.',
            ]);
        }

        $data = $request->validate([
            'returned_at' => ['nullable', 'date'],
            'return_observations' => ['nullable', 'string'],
            'return_signature_data_url' => [
                'required',
                'string',
                fn (string $attribute, mixed $value, Closure $fail) => str_starts_with((string) $value, 'data:image/png;base64,')
                    ? null
                    : $fail('La firma de devolución debe ser una imagen PNG generada desde el cuadro de firma.'),
            ],
        ]);

        $deliveryAct->forceFill([
            'returned_at' => $data['returned_at'] ?? now(),
            'return_observations' => $data['return_observations'] ?? null,
            'return_signature_data_url' => $data['return_signature_data_url'],
        ])->save();

        return back()->with('success', 'Equipo(s) marcado(s) como devuelto(s). El historial fue actualizado.');
    }

    public function history(Request $request): Response
    {
        $this->authorizeInventory($request);

        $equipment = InventoryTechEquipment::query()
            ->with(['deliveryItems:id,equipment_id,delivery_act_id', 'deliveryItems.deliveryAct:id,codigo,person_name,person_rut,departamento,cargo,delivered_at,returned_at,return_observations,condicion'])
            ->orderBy('marca')
            ->orderBy('numero_serie')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inventory/TechEquipmentDeliveries/History', [
            'equipment' => $equipment,
        ]);
    }

    private function presentAct(InventoryTechEquipmentDeliveryAct $act, bool $includeSignatures = false): array
    {
        return [
            'id' => $act->id,
            'codigo' => $act->codigo,
            'person_name' => $act->person_name,
            'person_rut' => $act->person_rut,
            'departamento' => $act->departamento,
            'cargo' => $act->cargo,
            'condicion' => $act->condicion,
            'delivered_at' => optional($act->delivered_at)->toISOString(),
            'observations' => $act->observations,
            'returned_at' => optional($act->returned_at)->toISOString(),
            'return_observations' => $act->return_observations,
            'signature_data_url' => $includeSignatures ? $act->signature_data_url : null,
            'return_signature_data_url' => $includeSignatures ? $act->return_signature_data_url : null,
            'creator' => $act->creator ? [
                'id' => $act->creator->id,
                'name' => $act->creator->name,
            ] : null,
            'items' => $act->items->map(fn ($item) => [
                'id' => $item->id,
                'equipment' => $item->equipment ? [
                    'id' => $item->equipment->id,
                    'marca' => $item->equipment->marca,
                    'fecha' => optional($item->equipment->fecha)->format('Y-m-d'),
                    'numero_serie' => $item->equipment->numero_serie,
                    'descripcion' => $item->equipment->descripcion,
                ] : null,
            ])->values(),
        ];
    }
}
