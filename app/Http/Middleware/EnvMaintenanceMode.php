<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnvMaintenanceMode
{
    public function handle(Request $request, Closure $next)
    {
        $enabled = filter_var(env('APP_MAINTENANCE', false), FILTER_VALIDATE_BOOLEAN);

        if ($enabled) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sitio en mantención. Inténtalo más tarde.'], 503);
            }

            return response()->view('maintenance', [], 503);
        }

        return $next($request);
    }
}
