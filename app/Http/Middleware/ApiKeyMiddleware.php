<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('termo.api_key');
        $token = $request->bearerToken();

        if ($expected === '' || $token === null || ! hash_equals($expected, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'details' => 'Se requiere el header Authorization: Bearer <token> con una API key válida',
                ],
            ], 401);
        }

        return $next($request);
    }
}
