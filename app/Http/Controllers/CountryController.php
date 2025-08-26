<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Continent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CountryController extends Controller
{
    public function index()
    {
        $countries = Country::with('continent')->get();
        $continents = Continent::all();
        return Inertia::render('Documentation/Countries/Index', [
            'countries' => $countries,
            'continents' => $continents,
        ]);
    }

    public function create()
    {
        $continents = Continent::all();
        return Inertia::render('Documentation/Countries/Create', [
            'continents' => $continents,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:countries,name',
            'continent_id' => 'required|exists:continents,id',
        ]);

        Country::create($validated);

        return redirect()->route('countries.index')->with('success', 'País creado exitosamente.');
    }

    public function update(Request $request, Country $country)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:countries,name,' . $country->id,
            'continent_id' => 'required|exists:continents,id',
        ]);

        $country->update($validated);

        return redirect()->route('countries.index')->with('success', 'País actualizado exitosamente.');
    }

    public function destroy(Country $country)
    {
        $country->delete();

        return redirect()->route('countries.index')->with('success', 'País eliminado exitosamente.');
    }
}
