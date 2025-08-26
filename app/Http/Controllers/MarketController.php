<?php

namespace App\Http\Controllers;

use App\Models\Market;
use App\Models\Country;
use App\Models\CertificateType;
use App\Models\AuthorizationType;
use App\Models\Especie;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MarketController extends Controller
{

        public function index(Request $request)
        {
            $search = $request->input('search');

            $markets = Market::query()
                ->when($search, function ($query, $search) {
                    $query->whereHas('country', function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhere('treatment_requirements', 'like', '%' . $search . '%')
                    ->orWhere('other_requirements', 'like', '%' . $search . '%')
                    ->orWhere('sampling_level', 'like', '%' . $search . '%')
                    ->orWhere('quarantine_pests', 'like', '%' . $search . '%');
                })
                ->with('country', 'certificateTypes', 'authorizationType', 'especies.variedads', 'variedads') // Eager load relationships
                ->paginate(10); // Paginate with 10 items per page

            return Inertia::render('Documentation/Markets/Index', [
                'markets' => $markets,
                'countries' => Country::all(), // Assuming you still need all countries for the dropdown
               'certificateTypes' => CertificateType::all(), // Assuming you still need all certificate types for the dropdown
                'authorizationTypes' => AuthorizationType::all(), // Add this line
                'especies' => Especie::all(), // Add this line
                'filters' => [
                    'search' => $search,
                ],
            ]);
        }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'treatment_requirements' => 'nullable|string',
            'other_requirements' => 'nullable|string',
            'sampling_level' => 'nullable|string|max:255',
            'quarantine_pests' => 'nullable|string',
            'is_active' => 'boolean',
            'authorization_type_id' => 'nullable|exists:authorization_types,id',
            'certificate_type_ids' => 'array',
            'certificate_type_ids.*' => 'exists:certificate_types,id',
            'especie_ids' => 'array',
            'especie_ids.*' => 'exists:especies,id',
            'variedad_ids' => 'array',
            'variedad_ids.*' => 'exists:variedads,id',
        ]);

        $market = Market::create($validated);
        $market->certificateTypes()->sync($validated['certificate_type_ids'] ?? []);
        $market->especies()->sync($validated['especie_ids'] ?? []);
        $market->variedads()->sync($validated['variedad_ids'] ?? []);

        return redirect()->route('markets.index')->with('success', 'Mercado creado exitosamente.');
    }

    public function update(Request $request, Market $market)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'treatment_requirements' => 'nullable|string',
            'other_requirements' => 'nullable|string',
            'sampling_level' => 'nullable|string|max:255',
            'quarantine_pests' => 'nullable|string',
            'is_active' => 'boolean',
            'authorization_type_id' => 'nullable|exists:authorization_types,id',
            'certificate_type_ids' => 'array',
            'certificate_type_ids.*' => 'exists:certificate_types,id',
            'especie_ids' => 'array',
            'especie_ids.*' => 'exists:especies,id',
            'variedad_ids' => 'array',
            'variedad_ids.*' => 'exists:variedads,id',
        ]);

        $market->update($validated);
        $market->certificateTypes()->sync($validated['certificate_type_ids'] ?? []);
        $market->especies()->sync($validated['especie_ids'] ?? []);
        $market->variedads()->sync($validated['variedad_ids'] ?? []);

        return redirect()->route('markets.index')->with('success', 'Mercado actualizado exitosamente.');
    }

    public function destroy(Market $market)
    {
        $market->delete();

        return redirect()->route('markets.index')->with('success', 'Mercado eliminado exitosamente.');
    }

    public function getVariedadesByEspecie(Especie $especie)
    {
        return response()->json($especie->variedads);
    }

    public function getMarketsByEspecie(Especie $especie)
    {
        // Eager load the country relationship for each market
        $markets = $especie->markets()->with('country')->get();

        // Transform the markets to include country name directly
        $transformedMarkets = $markets->map(function ($market) {
            return [
                'id' => $market->id,
                'name' => $market->country->name, // Assuming market has a country relationship
                'country_id' => $market->country->id,
                // Add any other relevant market details here
            ];
        });

        return response()->json($transformedMarkets);
    }
}
