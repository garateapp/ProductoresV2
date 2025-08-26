<?php

namespace App\Http\Controllers;

use App\Models\Telefono;
use Illuminate\Http\Request;

class TelefonoController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'numero' => 'required|string|max:255',
        ]);

        $telefono = new Telefono($request->all());
        $telefono->save();

        return redirect()->route('producers.edit', $request->user_id)->with('success', 'Telefono added successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Telefono $telefono)
    {
        $request->validate([
            'numero' => 'required|string|max:255',
        ]);

        $telefono->update($request->all());

        return redirect()->route('producers.edit', $telefono->user_id)->with('success', 'Telefono updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Telefono $telefono)
    {
        $producerId = $telefono->user_id;
        $telefono->delete();

        return redirect()->route('producers.edit', $producerId)->with('success', 'Telefono deleted successfully.');
    }
}
