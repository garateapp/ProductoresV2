<?php

namespace App\Http\Controllers;

use App\Models\Telefono;
use App\Models\User;
use Illuminate\Http\Request;

class TelefonoController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'numero' => 'required|string|max:255',
            'tipo' => 'nullable|string|max:255',
            'sync_same_rut' => 'sometimes|boolean',
        ]);

        $telefono = Telefono::create([
            'user_id' => $validated['user_id'],
            'numero' => $validated['numero'],
            'tipo' => $validated['tipo'] ?? null,
        ]);

        if ($request->boolean('sync_same_rut')) {
            $this->syncAcrossSameRut($telefono);
        }

        return redirect()->route('producers.edit', $request->user_id)->with('success', 'Telefono added successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Telefono $telefono)
    {
        $validated = $request->validate([
            'numero' => 'required|string|max:255',
            'tipo' => 'nullable|string|max:255',
            'sync_same_rut' => 'sometimes|boolean',
        ]);

        $originalNumero = $telefono->numero;

        $telefono->update([
            'numero' => $validated['numero'],
            'tipo' => $validated['tipo'] ?? $telefono->tipo,
        ]);

        if ($request->boolean('sync_same_rut')) {
            $this->syncAcrossSameRut($telefono, $originalNumero);
        }

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

    private function syncAcrossSameRut(Telefono $telefono, ?string $originalNumero = null): void
    {
        $owner = $telefono->user()->first();

        if (! $owner || empty($owner->rut)) {
            return;
        }

        $targetUsers = User::where('rut', $owner->rut)
            ->where('id', '!=', $owner->id)
            ->get();

        foreach ($targetUsers as $user) {
            // If we're updating an existing number, try to update matching phones
            if ($originalNumero) {
                $matchingPhone = $user->telefonos()->where('numero', $originalNumero)->first();
                if ($matchingPhone) {
                    $matchingPhone->update([
                        'numero' => $telefono->numero,
                        'tipo' => $telefono->tipo,
                    ]);
                    continue;
                }
            }

            // Avoid duplicates
            $exists = $user->telefonos()->where('numero', $telefono->numero)->exists();

            if (! $exists) {
                Telefono::create([
                    'user_id' => $user->id,
                    'numero' => $telefono->numero,
                    'tipo' => $telefono->tipo,
                ]);
            }
        }
    }
}
