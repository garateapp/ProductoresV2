<?php

namespace App\Http\Middleware;

use App\Services\Planning\SeasonContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve la temporada activa (`?temporada=actual|anterior`) antes de que corra
 * cualquier controller, para que los repositories lean de la conexión correcta.
 */
class ResolveSeasonContext
{
    public function __construct(private readonly SeasonContext $seasons) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->seasons->resolve($request);

        return $next($request);
    }
}
