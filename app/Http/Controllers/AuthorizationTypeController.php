<?php

namespace App\Http\Controllers;

use App\Models\AuthorizationType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuthorizationTypeController extends Controller
{
    public function index()
    {
        $authorizationTypes = AuthorizationType::all();
        return Inertia::render('Documentation/AuthorizationTypes/Index', [
            'authorizationTypes' => $authorizationTypes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:authorization_types,name',
        ]);

        AuthorizationType::create($validated);

        return redirect()->route('authorization-types.index')->with('success', 'Tipo de autorización creado exitosamente.');
    }

    public function update(Request $request, AuthorizationType $authorizationType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:authorization_types,name,' . $authorizationType->id,
        ]);

        $authorizationType->update($validated);

        return redirect()->route('authorization-types.index')->with('success', 'Tipo de autorización actualizado exitosamente.');
    }

    public function destroy(AuthorizationType $authorizationType)
    {
        $authorizationType->delete();

        return redirect()->route('authorization-types.index')->with('success', 'Tipo de autorización eliminado exitosamente.');
    }
}
