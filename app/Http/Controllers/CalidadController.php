<?php

namespace App\Http\Controllers;

use App\Models\Calidad;
use App\Models\Recepcion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalidadController extends Controller
{
    public function create()
    {
        $recepciones = Recepcion::all();
        return Inertia::render('Calidad/Create', [
            'recepciones' => $recepciones,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'recepcion_id' => 'required|exists:recepcions,id',
            'cantidad_total_muestra' => 'required|numeric',
            'materia_vegetal' => 'required|numeric',
            'piedras' => 'required|numeric',
            'barro' => 'required|numeric',
            'pedicelo_largo' => 'required|numeric',
            'racimo' => 'required|numeric',
            'esponjas' => 'required|numeric',
            'h_esponjas' => 'required|numeric',
            'llenado_tottes' => 'required|numeric',
            'embalaje' => 'required|numeric',
        ]);

        $calidad = Calidad::create($request->all());

        return redirect()->route('calidad.show', $calidad->id)
            ->with('success', 'Calidad creada exitosamente.');
    }

    public function show(Calidad $calidad)
    {
        $calidad->load('detalles.parametro', 'detalles.valor', 'recepcion');
        $parametros = Parametro::all();
        $valores = Valor::all();
        return Inertia::render('Calidad/Show', [
            'calidad' => $calidad,
            'parametros' => $parametros,
            'valores' => $valores,
        ]);
    }
}
