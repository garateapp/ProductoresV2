<?php

namespace App\Http\Controllers;

use App\Models\Continent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContinentController extends Controller
{
    public function index()
    {
        $continents = Continent::all();
        return Inertia::render('Documentation/Continents/Index', [
            'continents' => $continents,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:continents,name',
        ]);

        Continent::create($validated);

        return redirect()->route('continents.index')->with('success', 'Continente creado exitosamente.');
    }

    public function update(Request $request, Continent $continent)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:continents,name,' . $continent->id,
        ]);

        $continent->update($validated);

        return redirect()->route('continents.index')->with('success', 'Continente actualizado exitosamente.');
    }

    public function destroy(Continent $continent)
    {
        $continent->delete();

        return redirect()->route('continents.index')->with('success', 'Continente eliminado exitosamente.');
    }
}