<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryLabel;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LabelController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $labels = InventoryLabel::query()
            ->with('service:id,name')
            ->orderBy('codigo')
            ->get()
            ->map(fn (InventoryLabel $label) => [
                'id' => $label->id,
                'codigo' => $label->codigo,
                'nombre' => $label->nombre,
                'service_id' => $label->service_id,
                'service' => $label->service ? [
                    'id' => $label->service->id,
                    'name' => $label->service->name,
                ] : null,
                'activo' => $label->activo,
                'technical_sheets_count' => $label->technicalSheets()->count(),
            ]);

        return Inertia::render('Inventory/Labels/Index', [
            'labels' => $labels,
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $this->validated($request);

        InventoryLabel::create([
            'codigo' => $this->normalizedCode($data['codigo']),
            'nombre' => trim($data['nombre']),
            'service_id' => $data['service_id'] ?? null,
            'activo' => (bool) ($data['activo'] ?? true),
        ]);

        return back()->with('success', 'Etiqueta creada.');
    }

    public function update(Request $request, InventoryLabel $label): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $this->validated($request, $label);

        $label->fill([
            'codigo' => $this->normalizedCode($data['codigo']),
            'nombre' => trim($data['nombre']),
            'service_id' => $data['service_id'] ?? null,
            'activo' => (bool) ($data['activo'] ?? true),
        ])->save();

        return back()->with('success', 'Etiqueta actualizada.');
    }

    public function destroy(Request $request, InventoryLabel $label): RedirectResponse
    {
        $this->authorizeInventory($request);

        $label->delete();

        return back()->with('success', 'Etiqueta eliminada.');
    }

    private function validated(Request $request, ?InventoryLabel $label = null): array
    {
        return $request->validate([
            'codigo' => [
                'required',
                'string',
                'max:50',
                Rule::unique('inventory_labels', 'codigo')->ignore($label?->id),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'activo' => ['boolean'],
        ], [], [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'service_id' => 'servicio',
        ]);
    }

    private function normalizedCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }
}
