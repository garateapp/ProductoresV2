<?php

namespace App\Services\Planning;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * Temporada activa para las lecturas de inventario en SQL Server.
 *
 * - 'actual'   -> conexión 'sqlsrv' (temporada en curso)
 * - 'anterior' -> conexión 'temporada_anterior'
 *
 * La temporada se resuelve en este orden:
 * 1. Query string `?temporada=anterior` (lo que envía el switch de la UI).
 * 2. Valor persistido en sesión.
 * 3. 'actual' (default seguro).
 */
class SeasonContext
{
    public const ACTUAL = 'actual';

    public const ANTERIOR = 'anterior';

    public const SESSION_KEY = 'planning.temporada';

    public const QUERY_PARAM = 'temporada';

    /**
     * Conexión SQL Server asociada a cada temporada.
     *
     * @var array<string, string>
     */
    public const CONNECTIONS = [
        self::ACTUAL => 'sqlsrv',
        self::ANTERIOR => 'temporada_anterior',
    ];

    public function __construct(private readonly Session $session) {}

    /**
     * Temporada efectiva de la request actual.
     */
    public function current(): string
    {
        return $this->normalize($this->session->get(self::SESSION_KEY, self::ACTUAL));
    }

    /**
     * Fija la temporada (y la deja persistida en sesión).
     */
    public function set(mixed $season): string
    {
        $season = $this->normalize($season);
        $this->session->put(self::SESSION_KEY, $season);

        return $season;
    }

    /**
     * Conexión SQL Server a usar para la temporada indicada (o la actual).
     */
    public function connection(mixed $season = null): string
    {
        $season = $season !== null ? $this->normalize($season) : $this->current();

        return self::CONNECTIONS[$season] ?? self::CONNECTIONS[self::ACTUAL];
    }

    public function isPrevious(mixed $season = null): bool
    {
        $season = $season !== null ? $this->normalize($season) : $this->current();

        return $season === self::ANTERIOR;
    }

    /**
     * Resuelve la temporada de la request y la persiste para las siguientes.
     *
     * Un valor desconocido en el query string se ignora (no se cae a 'anterior').
     */
    public function resolve(?Request $request = null): string
    {
        $request ??= request();

        $requested = $request->query(self::QUERY_PARAM);
        if ($this->isValid($requested)) {
            return $this->set($requested);
        }

        return $this->set($this->current());
    }

    public function isValid(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), [self::ACTUAL, self::ANTERIOR], true);
    }

    private function normalize(mixed $value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($value, [self::ACTUAL, self::ANTERIOR], true) ? $value : self::ACTUAL;
    }
}
