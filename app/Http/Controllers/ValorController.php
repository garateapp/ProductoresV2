<?php

namespace App\Http\Controllers;

use App\Models\Especie;
use App\Models\Parametro;
use App\Models\Valor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ValorController extends Controller
{
    public function index()
    {
        $valores = Valor::with('parametro')->latest()->paginate(10);

        return Inertia::render('Valors/Index', [
            'valores' => $valores,
        ]);
    }

    public function create()
    {
        return Inertia::render('Valors/Create', [
            'parametros' => Parametro::orderBy('name')->get(['id', 'name']),
            'especies' => Especie::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parametro_id' => 'required|exists:parametros,id',
            'especie' => 'nullable|string|max:255',
            'variedad' => 'nullable|string|max:255',
            'informe' => 'nullable|string|max:255',
        ]);

        Valor::create($data);

        return redirect()->route('valores.index')->with('success', 'Valor creado correctamente.');
    }

    public function edit(Valor $valor)
    {
        return Inertia::render('Valors/Edit', [
            'valor' => $valor->load('parametro'),
            'parametros' => Parametro::orderBy('name')->get(['id', 'name']),
            'especies' => Especie::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Valor $valor)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parametro_id' => 'required|exists:parametros,id',
            'especie' => 'nullable|string|max:255',
            'variedad' => 'nullable|string|max:255',
            'informe' => 'nullable|string|max:255',
        ]);

        $valor->update($data);

        return redirect()->route('valores.index')->with('success', 'Valor actualizado correctamente.');
    }

    public function destroy(Valor $valor)
    {
        $valor->delete();

        return redirect()->route('valores.index')->with('success', 'Valor eliminado correctamente.');
    }
}
