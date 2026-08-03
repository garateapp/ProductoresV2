<?php

namespace App\Services\Integrations\Engine;

use App\Contracts\Integrations\OutputExporterInterface;
use InvalidArgumentException;

class OutputExporterFactory
{
    private static array $exporters = [];

    public static function register(OutputExporterInterface $exporter): void
    {
        self::$exporters[$exporter->key()] = $exporter;
    }

    public static function create(string $key): OutputExporterInterface
    {
        if (isset(self::$exporters[$key])) {
            return self::$exporters[$key];
        }

        $class = self::resolveClass($key);

        if ($class && class_exists($class)) {
            $exporter = app($class);

            if ($exporter instanceof OutputExporterInterface) {
                return $exporter;
            }
        }

        throw new InvalidArgumentException("Output exporter not found: {$key}");
    }

    public static function available(): array
    {
        $exporters = [];

        foreach (self::$exporters as $key => $exporter) {
            $exporters[$key] = $exporter->label();
        }

        $builtIn = [
            'excel' => 'Excel',
            'csv' => 'CSV',
            'json' => 'JSON',
        ];

        return array_merge($builtIn, $exporters);
    }

    private static function resolveClass(string $key): ?string
    {
        $map = [
            'excel' => \App\Services\Integrations\Exporters\ExcelExporter::class,
            'csv' => \App\Services\Integrations\Exporters\CsvExporter::class,
            'json' => \App\Services\Integrations\Exporters\JsonExporter::class,
        ];

        return $map[$key] ?? null;
    }
}
