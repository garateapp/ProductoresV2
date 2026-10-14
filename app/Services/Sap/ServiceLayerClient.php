<?php

namespace App\Services\Sap;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class ServiceLayerClient
{
    private const SESSION_CACHE_KEY = 'sap_service_layer.session';

    private const MAX_SQL_PAGES = 10000;

    public function sessionId(): string
    {
        return Cache::remember(self::SESSION_CACHE_KEY, now()->addMinutes(25), function (): string {
            $response = $this->http()
                ->post($this->baseUrl().'/Login', [
                    'CompanyDB' => config('services.sap_service_layer.company_db'),
                    'UserName' => config('services.sap_service_layer.username'),
                    'Password' => config('services.sap_service_layer.password'),
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('SAP Service Layer: fallo el login ('.$response->status().').');
            }

            $sessionId = $response->json('SessionId');

            if (! is_string($sessionId) || $sessionId === '') {
                throw new RuntimeException('SAP Service Layer: login sin SessionId.');
            }

            return $sessionId;
        });
    }

    public function sqlQuery(string $name, array $params = []): Collection
    {
        $url = $this->baseUrl().'/SQLQueries(\''.$name.'\')/List';
        $query = $params;
        $rows = collect();
        $pages = 0;

        while (true) {
            $response = $this->sqlRequest($url, $query, $this->sessionId());

            if ($response->status() === 401) {
                Cache::forget(self::SESSION_CACHE_KEY);
                $response = $this->sqlRequest($url, $query, $this->sessionId());
            }

            if (! $response->successful()) {
                throw new RuntimeException($this->errorMessage($response));
            }

            $payload = $response->json() ?? [];

            foreach (($payload['value'] ?? []) as $row) {
                $rows->push((object) $row);
            }

            // Paginación dirigida por el servidor: `odata.nextLink` ya incluye
            // todos los parámetros de la consulta.
            $next = $payload['odata.nextLink'] ?? $payload['@odata.nextLink'] ?? null;

            if (! is_string($next) || $next === '') {
                break;
            }

            if (++$pages >= self::MAX_SQL_PAGES) {
                throw new RuntimeException('SAP Service Layer: se excedió el límite de páginas al consultar '.$name.'.');
            }

            $url = $this->absoluteUrl($next);
            $query = [];
        }

        return $rows;
    }

    public function entitySet(string $name, array $params = []): Collection
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException('Nombre de EntitySet SAP inválido.');
        }

        $pageSize = 1000;
        $offset = 0;
        $rows = collect();

        do {
            $pageParams = [
                ...$params,
                '$top' => $pageSize,
                '$skip' => $offset,
            ];
            $response = $this->entitySetRequest($name, $pageParams, $this->sessionId());

            if ($response->status() === 401) {
                Cache::forget(self::SESSION_CACHE_KEY);
                $response = $this->entitySetRequest($name, $pageParams, $this->sessionId());
            }

            if (! $response->successful()) {
                throw new RuntimeException($this->errorMessage($response));
            }

            $page = collect($response->json('value') ?? []);
            $rows = $rows->merge($page->map(fn ($row) => (object) $row));
            $offset += $page->count();
        } while ($page->count() === $pageSize);

        return $rows;
    }

    private function sqlRequest(string $url, array $params, string $sessionId): Response
    {
        $request = $this->http()
            ->withHeaders([
                'Cookie' => 'B1SESSION='.$sessionId,
                'Prefer' => 'odata.maxpagesize=1000',
            ]);

        // No pasar un array de query vacío: Guzzle reemplazaría la query que ya
        // viene embebida en la URL de `odata.nextLink`.
        return $params === []
            ? $request->get($url)
            : $request->get($url, $params);
    }

    private function entitySetRequest(string $name, array $params, string $sessionId): Response
    {
        return $this->http()
            ->withHeaders([
                'Cookie' => 'B1SESSION='.$sessionId,
                'Prefer' => 'odata.maxpagesize=1000',
            ])
            ->get($this->baseUrl().'/'.$name, $params);
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::timeout($this->timeout());

        if (! config('services.sap_service_layer.verify_ssl')) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.sap_service_layer.base_url'), '/');
    }

    private function absoluteUrl(string $url): string
    {
        $url = trim($url);

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        $parts = parse_url($this->baseUrl());
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        $origin .= isset($parts['port']) ? ':'.$parts['port'] : '';

        // SAP devuelve `odata.nextLink` relativo a la raíz del servicio (b1s/v1).
        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }

        if (str_starts_with($url, '?')) {
            return $this->baseUrl().$url;
        }

        return $this->baseUrl().'/'.$url;
    }

    private function timeout(): int
    {
        return (int) config('services.sap_service_layer.timeout', 60);
    }

    private function errorMessage(Response $response): string
    {
        $body = $response->json();
        $code = data_get($body, 'error.code');
        $message = data_get($body, 'error.message.value') ?? data_get($body, 'error.message');

        if (is_array($message)) {
            $message = json_encode($message);
        }

        $prefix = 'SAP Service Layer error';
        if ($code !== null) {
            $prefix .= ' ['.$code.']';
        }

        return $prefix.': '.($message ?: 'HTTP '.$response->status());
    }
}
