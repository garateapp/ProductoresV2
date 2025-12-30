<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnvMaintenanceMode
{
    public function handle(Request $request, Closure $next)
    {
        $enabled = filter_var(env('APP_MAINTENANCE', false), FILTER_VALIDATE_BOOLEAN);

        if ($enabled) {
            // Solo bloquear a productores (u otros usuarios con rol "Productor") una vez autenticados
            $user = Auth::user();
            if ($user && method_exists($user, 'hasRole') && $user->hasRole('Productor')) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Sitio en mantención. Inténtalo más tarde.'], 503);
                }

                return response()->view('maintenance', [], 503);
            }

            // Si no hay usuario autenticado (ej. pantalla de login), permitir acceso
            if (! $user) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sitio en mantención. Inténtalo más tarde.'], 503);
            }

            return response()->view('maintenance', [], 503);
        }

        return $next($request);
    }
}
