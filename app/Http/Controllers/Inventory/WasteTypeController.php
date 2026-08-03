<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryWasteType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WasteTypeController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/WasteTypes/Index', [
            'types' => InventoryWasteType::all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => 'required|string|max:20|unique:inventory_waste_types',
            'nombre' => 'required|string|max:100',
            'permite_devolucion' => 'boolean',
        ]);

        InventoryWasteType::create([...$data, 'activo' => true]);

        return back()->with('success', 'Tipo de merma creado correctamente.');
    }

    public function update(Request $request, InventoryWasteType $wasteType)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:100',
            'activo' => 'boolean',
            'permite_devolucion' => 'boolean',
        ]);

        $wasteType->update($data);

        return back()->with('success', 'Tipo de merma actualizado.');
    }
}
