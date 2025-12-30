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
            if ($user && method_exists($user, 'hasRole')) {
                $hasProducer = $user->hasRole('Productor');
                $hasBypassRole = method_exists($user, 'hasAnyRole')
                    ? $user->hasAnyRole(['Administrador', 'Admin', 'Calidad', 'Agronomo', 'Agrónomo', 'Gerencia'])
                    : ($user->hasRole('Administrador') || $user->hasRole('Admin') || $user->hasRole('Calidad') || $user->hasRole('Agronomo') || $user->hasRole('Agrónomo') || $user->hasRole('Gerencia'));

                if ( ! $hasBypassRole) {
                    if ($request->expectsJson()) {
                        return response()->json(['message' => 'Sitio en mantención. Inténtalo más tarde.'], 503);
                    }

                    return response()->view('maintenance', [], 503);
                }
            }

            // Si no hay usuario autenticado (ej. pantalla de login), permitir acceso
            if (! $user) {
                return $next($request);
            }

            // Usuarios autenticados con roles distintos a Productor pueden acceder normalmente
            if ($user && method_exists($user, 'hasRole') && ! $user->hasRole('Productor')) {
                return $next($request);
            }

            // Fallback: si no se pudo determinar, bloquear con vista
            if ($user) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Sitio en mantención. Inténtalo más tarde.'], 503);
                }

                return response()->view('maintenance', [], 503);
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sitio en mantención. Inténtalo más tarde.'], 503);
            }

            return response()->view('maintenance', [], 503);
        }

        return $next($request);
    }
}
