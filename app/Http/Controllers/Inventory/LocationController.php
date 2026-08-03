<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $locations = InventoryLocation::query()
            ->withCount('stocks')
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get()
            ->map(fn (InventoryLocation $location) => [
                'id' => $location->id,
                'codigo' => $location->codigo,
                'nombre' => $location->nombre,
                'tipo' => $location->tipo,
                'permite_stock_negativo' => $location->permite_stock_negativo,
                'activo' => $location->activo,
                'stocks_count' => $location->stocks_count,
                'es_bodega_central' => $location->es_bodega_central,
            ]);

        return Inertia::render('Inventory/Locations/Index', [
            'locations' => $locations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'unique:inventory_locations,codigo'],
            'nombre' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'string', 'max:50'],
            'permite_stock_negativo' => ['boolean'],
            'activo' => ['boolean'],
        ]);

        InventoryLocation::create($data);

        return back()->with('success', 'Ubicación creada.');
    }

    public function update(Request $request, InventoryLocation $location): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'string', 'max:50'],
            'permite_stock_negativo' => ['boolean'],
            'activo' => ['boolean'],
            'es_bodega_central' => ['boolean'],

        ]);

        $location->fill($data)->save();

        return back()->with('success', 'Ubicación actualizada.');
    }
}
