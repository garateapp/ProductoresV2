<?php

namespace App\Services\Sap;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ServiceLayerClient
{
    private const SESSION_CACHE_KEY = 'sap_service_layer.session';

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
        $response = $this->request($name, $params, $this->sessionId());

        if ($response->status() === 401) {
            Cache::forget(self::SESSION_CACHE_KEY);
            $response = $this->request($name, $params, $this->sessionId());
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response));
        }

        return collect($response->json('value') ?? [])
            ->map(fn ($row) => (object) $row);
    }

    private function request(string $name, array $params, string $sessionId): Response
    {
        $url = $this->baseUrl().'/SQLQueries(\''.$name.'\')/List';

        return $this->http()
            ->withHeaders(['Cookie' => 'B1SESSION='.$sessionId])
            ->get($url, $params);
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
