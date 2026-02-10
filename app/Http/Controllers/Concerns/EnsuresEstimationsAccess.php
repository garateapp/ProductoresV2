<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait EnsuresEstimationsAccess
{
    protected function ensureEstimationsAccess(Request $request): void
    {
        $user = $request->user();
        $allowed = $user
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['Admin', 'Administrador', 'Agronomo', 'Agrónomo']);

        abort_unless($allowed, 403);
    }
}