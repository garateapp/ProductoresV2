<?php

namespace App\Services\Integrations\Engine;

use App\Contracts\Integrations\SourceAdapterInterface;
use App\Models\IntegrationSourceAdapter;
use App\Services\Integrations\Adapters\GenericSourceAdapter;
use InvalidArgumentException;

class SourceAdapterFactory
{
    private static array $adapters = [];

    public static function register(SourceAdapterInterface $adapter): void
    {
        self::$adapters[$adapter->key()] = $adapter;
    }

    public static function create(string $key): SourceAdapterInterface
    {
        if (isset(self::$adapters[$key])) {
            return self::$adapters[$key];
        }

        $dbAdapter = IntegrationSourceAdapter::where('key', $key)
            ->where('activo', true)
            ->first();

        if ($dbAdapter) {
            return new GenericSourceAdapter($dbAdapter);
        }

        $class = self::resolveClass($key);

        if ($class && class_exists($class)) {
            $adapter = app($class);

            if ($adapter instanceof SourceAdapterInterface) {
                return $adapter;
            }
        }

        throw new InvalidArgumentException("Source adapter not found: {$key}");
    }

    public static function available(): array
    {
        $adapters = [];

        foreach (self::$adapters as $key => $adapter) {
            $adapters[$key] = $adapter->label();
        }

        $dbAdapters = IntegrationSourceAdapter::where('activo', true)
            ->get(['key', 'nombre']);

        foreach ($dbAdapters as $dbAdapter) {
            $adapters[$dbAdapter->key] = $dbAdapter->nombre;
        }

        return $adapters;
    }

    private static function resolveClass(string $key): ?string
    {
        $map = [
            'internal_database' => \App\Services\Integrations\Adapters\InternalDatabaseAdapter::class,
        ];

        return $map[$key] ?? null;
    }
}
