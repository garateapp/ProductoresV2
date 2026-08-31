<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryConsumptionOrigin;
use App\Models\InventoryLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsumptionOriginController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $origins = InventoryConsumptionOrigin::query()
            ->with('location')
            ->orderBy('linea')
            ->orderBy('turno')
            ->get()
            ->map(fn (InventoryConsumptionOrigin $origin) => [
                'id' => $origin->id,
                'linea' => $origin->linea,
                'turno' => $origin->turno,
                'location_id' => $origin->location_id,
                'location' => $origin->location ? [
                    'id' => $origin->location->id,
                    'codigo' => $origin->location->codigo,
                    'nombre' => $origin->location->nombre,
                ] : null,
                'activo' => $origin->activo,
            ]);

        $locations = InventoryLocation::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn (InventoryLocation $location) => [
                'id' => $location->id,
                'codigo' => $location->codigo,
                'nombre' => $location->nombre,
                'tipo' => $location->tipo,
            ]);

        return Inertia::render('Inventory/ConsumptionOrigins/Index', [
            'origins' => $origins,
            'locations' => $locations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'linea' => ['required', 'string', 'max:100'],
            'turno' => ['nullable', 'string', 'max:100'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'activo' => ['boolean'],
        ]);

        $data['turno'] = trim((string) ($data['turno'] ?? ''));

        InventoryConsumptionOrigin::create([...$data, 'activo' => $data['activo'] ?? true]);

        return back()->with('success', 'Origen de consumo creado.');
    }

    public function update(Request $request, InventoryConsumptionOrigin $origin): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'linea' => ['required', 'string', 'max:100'],
            'turno' => ['nullable', 'string', 'max:100'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'activo' => ['boolean'],
        ]);

        $origin->fill([...$data, 'turno' => trim((string) ($data['turno'] ?? ''))])->save();

        return back()->with('success', 'Origen de consumo actualizado.');
    }

    public function destroy(Request $request, InventoryConsumptionOrigin $origin): RedirectResponse
    {
        $this->authorizeInventory($request);

        $origin->delete();

        return back()->with('success', 'Origen de consumo eliminado.');
    }
}