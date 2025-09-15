<?php

namespace App\Http\Controllers;

use App\Models\Calidad;
use Illuminate\Http\Request;

class DetalleController extends Controller
{
    public function store(Request $request, Calidad $calidad)
    {
        $request->validate([
            'parametro_id' => 'required|exists:parametros,id',
            'valor_id' => 'required|exists:valors,id',
            'cantidad_muestra' => 'required|numeric',
            'exportable' => 'required|boolean',
        ]);

        $detalle = $calidad->detalles()->create($request->all());

        return redirect()->back()
            ->with('success', 'Detalle agregado exitosamente.');
    }
}
