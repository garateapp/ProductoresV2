<?php

namespace App\Services\Integrations\Adapters;

use App\Contracts\Integrations\SourceAdapterInterface;
use App\Models\IntegrationSourceAdapter;

class GenericSourceAdapter implements SourceAdapterInterface
{
    private IntegrationSourceAdapter $adapter;

    public function __construct(IntegrationSourceAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function key(): string
    {
        return $this->adapter->key;
    }

    public function label(): string
    {
        return $this->adapter->nombre;
    }

    public function validateConfiguration(array $configuration): void
    {
        $merged = $this->mergeConfig($configuration);
        $handler = $this->resolveHandler();
        $handler->validateConfiguration($merged);
    }

    public function getSchema(array $configuration): array
    {
        if (!empty($this->adapter->esquema_entrada)) {
            return $this->adapter->esquema_entrada;
        }

        $handler = $this->resolveHandler();
        return $handler->getSchema($this->mergeConfig($configuration));
    }

    public function count(array $configuration): int
    {
        $handler = $this->resolveHandler();
        return $handler->count($this->mergeConfig($configuration));
    }

    public function getRecords(array $configuration): \Generator
    {
        $handler = $this->resolveHandler();
        return $handler->getRecords($this->mergeConfig($configuration));
    }

    public function getStableIdentifier(array $record): string
    {
        $handler = $this->resolveHandler();
        return $handler->getStableIdentifier($record);
    }

    public function applyFilters(array $configuration, array $filters): array
    {
        return array_merge($configuration, [
            'filters' => $filters,
        ]);
    }

    public function getExamples(array $configuration): array
    {
        $handler = $this->resolveHandler();
        return $handler->getExamples($this->mergeConfig($configuration));
    }

    private function resolveHandler(): \App\Contracts\Integrations\SourceAdapterHandler
    {
        return match ($this->adapter->tipo_conexion) {
            'database' => app(\App\Services\Integrations\Adapters\Handlers\DatabaseHandler::class),
            'api_rest' => app(\App\Services\Integrations\Adapters\Handlers\ApiRestHandler::class),
            'archivo' => app(\App\Services\Integrations\Adapters\Handlers\FileHandler::class),
            'ftp' => app(\App\Services\Integrations\Adapters\Handlers\FtpHandler::class),
            default => throw new \InvalidArgumentException("Tipo de conexión no soportado: {$this->adapter->tipo_conexion}"),
        };
    }

    private function mergeConfig(array $runtimeConfig): array
    {
        return array_merge(
            $this->adapter->configuracion ?? [],
            $runtimeConfig
        );
    }
}
