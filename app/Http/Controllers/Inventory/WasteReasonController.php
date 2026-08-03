<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryWasteReason;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WasteReasonController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/WasteReasons/Index', [
            'reasons' => InventoryWasteReason::all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => 'required|string|max:20|unique:inventory_waste_reasons',
            'nombre' => 'required|string|max:100',
        ]);

        InventoryWasteReason::create([...$data, 'activo' => true]);

        return back()->with('success', 'Motivo de merma creado correctamente.');
    }

    public function update(Request $request, InventoryWasteReason $wasteReason)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:100',
            'activo' => 'boolean',
        ]);

        $wasteReason->update($data);

        return back()->with('success', 'Motivo de merma actualizado.');
    }
}
