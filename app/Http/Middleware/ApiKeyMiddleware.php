<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('services.termo.api_key'));
        $token = $request->bearerToken();

        $unauthorized = $expected === ''
            || $token === null
            || ! hash_equals($expected, trim($token));

        if ($unauthorized) {
            Log::warning('TERMO_ENDPOINT_UNAUTHORIZED', [
                'reason' => $expected === ''
                    ? 'config_api_key_empty'
                    : ($token === null ? 'missing_bearer_header' : 'token_mismatch'),
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

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
