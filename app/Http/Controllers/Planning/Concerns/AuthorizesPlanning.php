<?php

namespace App\Http\Controllers\Planning\Concerns;

use Illuminate\Http\Request;

trait AuthorizesPlanning
{
    protected function authorizePlanning(Request $request): void
    {
        $user = $request->user();
        $allowed = ['Admin', 'Administrador', 'Calidad', 'Gerencia'];

        $hasRole = method_exists($user, 'hasRole') && collect($allowed)->some(fn ($r) => $user->hasRole($r));
        abort_unless($hasRole, 403, 'No tienes permisos para acceder a Planificación.');
    }
}

