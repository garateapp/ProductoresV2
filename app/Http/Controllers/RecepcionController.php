<?php

namespace App\Http\Controllers;

use App\Models\Recepcion;
use App\Models\Especie;
use App\Models\User;
use App\Models\Variedad; // Add this line
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RecepcionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isProducer = false;

        if (!empty($user->idprod)) {
            $isProducer = true;
        }

        $query = Recepcion::query();

        // Apply producer-specific filter first
        if ($isProducer) {
            $query->where('n_emisor', $user->name); // Filter by producer's name
            $producerEspeciesNames = $user->especies()->pluck('name')->toArray(); // Get names, not IDs
            $query->whereIn('n_especie', $producerEspeciesNames);
        }

        // Filtro por variedad, especie o lote
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('n_variedad', 'like', '%' . $searchTerm . '%')
                  ->orWhere('n_especie', 'like', '%' . $searchTerm . '%');
                  // Add lote if it exists in recepcions table
                  // ->orWhere('lote', 'like', '%' . $searchTerm . '%');
            });
        }

        // Initialize variedades collection
        $variedades = collect();

        // Filtro por especie seleccionada (desde los botones)
        if ($request->has('especie_id') && $request->input('especie_id') !== '') {
            $especie = Especie::find($request->input('especie_id'));
            if ($especie) {
                $query->where('n_especie', $especie->name);
                // Load varieties for the selected species
                $variedades = $especie->variedads; // Assuming Especie model has a hasMany relationship to Variedad
            }
        }

        // Filtro por variedad seleccionada (desde los botones de variedad)
        if ($request->has('variedad_id') && $request->input('variedad_id') !== '') {
            $variedad = Variedad::find($request->input('variedad_id'));
            if ($variedad) {
                $query->where('n_variedad', $variedad->name);
            }
        }

        // Calculate totals before pagination
        $totalRecepciones = $query->count();
        $totalKilos = (int) $query->sum('peso_neto');

        $recepciones = $query->paginate(10); // Paginación de 10 elementos por página

        $especies = Especie::all();

        // Filtrar especies si el usuario es productor
        if ($isProducer) {
            $producerEspeciesIds = $user->especies()->pluck('especie_id')->toArray();
            $especies = $especies->whereIn('id', $producerEspeciesIds)->values();
        }

        return Inertia::render('Recepciones/Index', [
            'recepciones' => $recepciones,
            'especies' => $especies->toArray(), // Convertir a array aquí
            'variedades' => $variedades, // Pass varieties to the frontend
            'filters' => $request->only(['search', 'especie_id', 'variedad_id']),
            'isProducer' => $isProducer,
            'totalRecepciones' => $totalRecepciones, // Pass total recepciones
            'totalKilos' => $totalKilos,             // Pass total kilos
        ]);

    }
}
