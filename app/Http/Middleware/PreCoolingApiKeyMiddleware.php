<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PreCoolingApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('services.precooling.api_key'));
        $token = $request->bearerToken();

        if ($expected === '' || $token === null || ! hash_equals($expected, trim($token))) {
            Log::warning('PRECOOLING_ENDPOINT_UNAUTHORIZED', [
                'reason' => $expected === ''
                    ? 'config_api_key_empty'
                    : ($token === null ? 'missing_bearer_header' : 'token_mismatch'),
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'isSucess' => false,
                'isPartialSuccess' => null,
                'data' => [],
                'message' => 'No autorizado',
            ], 401);
        }

        return $next($request);
    }
}
