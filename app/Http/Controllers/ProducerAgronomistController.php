<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ProducerAgronomistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $agronomists = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Agronomo', 'Agrónomo']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json($agronomists);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, User $producer)
    {
        $request->validate([
            'agronomist_id' => 'required|exists:users,id',
        ]);

        $agronomistId = (int) $request->input('agronomist_id');

        $isAgronomist = User::query()
            ->whereKey($agronomistId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Agronomo', 'Agrónomo']))
            ->exists();

        if (! $isAgronomist) {
            return back()->withErrors([
                'agronomist_id' => 'El usuario seleccionado no es un agrónomo válido.',
            ]);
        }

        $producer->agronomists()->syncWithoutDetaching([
            $agronomistId => ['rol' => 'admin'],
        ]);

        return back()->with('success', 'Agrónomo asociado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $producer)
    {
        $request->validate([
            'agronomist_id' => 'required|exists:users,id',
        ]);

        $producer->agronomists()->detach([(int) $request->input('agronomist_id')]);

        return back()->with('success', 'Agrónomo desasociado exitosamente.');
    }
}
