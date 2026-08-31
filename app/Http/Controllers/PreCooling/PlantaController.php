<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PreCooling\Concerns\AuthorizesPreCooling;
use App\Models\PreCoolingBodega;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlantaController extends Controller
{
    use AuthorizesPreCooling;

    public function __invoke(Request $request)
    {
        $this->authorizePreCooling($request);

        $bodegas = PreCoolingBodega::where('activo', true)
            ->orderBy('pos_x')
            ->orderBy('pos_y')
            ->get();

        $mapZone = fn ($b, $tipo) => [
            'id' => $b->id,
            'codigo' => $b->codigo,
            'nombre' => $b->nombre,
            'filas' => $b->filas,
            'columnas' => $b->columnas,
            'alto_maximo' => $b->alto_maximo,
            'pos_x' => $b->pos_x,
            'pos_y' => $b->pos_y,
            'tipo' => $tipo,
        ];

        return Inertia::render('PreCooling/PlantaLayout', [
            'tuneles' => $bodegas->filter(fn ($b) => str_starts_with($b->codigo, 'TN'))
                ->map(fn ($b) => $mapZone($b, 'tunel'))
                ->values(),
            'camaras' => $bodegas->filter(fn ($b) => str_starts_with($b->codigo, 'CA'))
                ->map(fn ($b) => $mapZone($b, 'camara'))
                ->values(),
            'productoTerminado' => $bodegas->filter(fn ($b) => str_starts_with($b->codigo, 'PT'))
                ->map(fn ($b) => $mapZone($b, 'pt'))
                ->values(),
        ]);
    }
}
