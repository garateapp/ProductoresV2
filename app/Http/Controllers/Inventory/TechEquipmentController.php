<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryTechEquipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TechEquipmentController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $equipment = InventoryTechEquipment::query()
            ->orderBy('marca')
            ->orderBy('numero_serie')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inventory/TechEquipment/Index', [
            'equipment' => $equipment,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'marca' => ['required', 'string', 'max:150'],
            'fecha' => ['nullable', 'date'],
            'numero_serie' => ['required', 'string', 'max:120', 'unique:inventory_tech_equipment,numero_serie'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ]);

        InventoryTechEquipment::create($data);

        return back()->with('success', 'Equipo tecnológico creado.');
    }

    public function update(Request $request, InventoryTechEquipment $techEquipment): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'marca' => ['required', 'string', 'max:150'],
            'fecha' => ['nullable', 'date'],
            'numero_serie' => ['required', 'string', 'max:120', 'unique:inventory_tech_equipment,numero_serie,'.$techEquipment->id],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ]);

        $techEquipment->fill($data)->save();

        return back()->with('success', 'Equipo tecnológico actualizado.');
    }
}
