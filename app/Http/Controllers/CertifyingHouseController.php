<?php

namespace App\Http\Controllers;

use App\Models\CertifyingHouse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CertifyingHouseController extends Controller
{
    public function index()
    {
        $certifyingHouses = CertifyingHouse::all();
        return Inertia::render('Documentation/CertifyingHouses/Index', [
            'certifyingHouses' => $certifyingHouses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:certifying_houses,name',
        ]);

        CertifyingHouse::create($validated);

        return redirect()->route('certifying-houses.index')->with('success', 'Casa certificadora creada exitosamente.');
    }

    public function update(Request $request, CertifyingHouse $certifyingHouse)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:certifying_houses,name,' . $certifyingHouse->id,
        ]);

        $certifyingHouse->update($validated);

        return redirect()->route('certifying-houses.index')->with('success', 'Casa certificadora actualizada exitosamente.');
    }

    public function destroy(CertifyingHouse $certifyingHouse)
    {
        $certifyingHouse->delete();

        return redirect()->route('certifying-houses.index')->with('success', 'Casa certificadora eliminada exitosamente.');
    }
}
