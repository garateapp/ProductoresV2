<?php

namespace App\Services\Integrations\Adapters\Handlers;

use App\Contracts\Integrations\SourceAdapterHandler;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

class FileHandler implements SourceAdapterHandler
{
    public function validateConfiguration(array $config): void
    {
        if (empty($config['path']) && empty($config['contents'])) {
            throw new \InvalidArgumentException('Debe especificar una ruta o contenido del archivo');
        }

        $format = $config['format'] ?? 'csv';
        if (!in_array($format, ['csv', 'json', 'excel', 'xml'])) {
            throw new \InvalidArgumentException("Formato no soportado: {$format}");
        }
    }

    public function getSchema(array $config): array
    {
        $examples = $this->getExamples($config);

        if (empty($examples)) {
            return [];
        }

        $first = $examples[0];
        $schema = [];

        foreach ($first as $key => $value) {
            $schema[] = [
                'name' => $key,
                'type' => gettype($value),
            ];
        }

        return $schema;
    }

    public function count(array $config): int
    {
        $records = iterator_to_array($this->getRecords($config));
        return count($records);
    }

    public function getRecords(array $config): \Generator
    {
        $format = $config['format'] ?? 'csv';
        $records = $this->parseFile($config);

        foreach ($records as $record) {
            yield $record;
        }
    }

    public function getExamples(array $config): array
    {
        $records = [];
        $count = 0;

        foreach ($this->getRecords($config) as $record) {
            $records[] = $record;
            $count++;

            if ($count >= 5) {
                break;
            }
        }

        return $records;
    }

    public function getStableIdentifier(array $record): string
    {
        return (string) ($record['id'] ?? $record['ID'] ?? md5(json_encode($record)));
    }

    private function parseFile(array $config): LazyCollection
    {
        $format = $config['format'] ?? 'csv';
        $contents = $this->readContents($config);

        return match ($format) {
            'csv' => $this->parseCsv($contents, $config),
            'json' => $this->parseJson($contents, $config),
            'excel' => $this->parseExcel($config),
            'xml' => $this->parseXml($contents),
            default => throw new \InvalidArgumentException("Formato no soportado: {$format}"),
        };
    }

    private function readContents(array $config): string
    {
        if (!empty($config['contents'])) {
            return $config['contents'];
        }

        $disk = $config['disk'] ?? 'local';
        $path = $config['path'] ?? '';

        if (!Storage::disk($disk)->exists($path)) {
            throw new \InvalidArgumentException("Archivo no encontrado: {$path}");
        }

        return Storage::disk($disk)->get($path);
    }

    private function parseCsv(string $contents, array $config): LazyCollection
    {
        $delimiter = $config['delimiter'] ?? ',';
        $hasHeader = $config['has_header'] ?? true;
        $encoding = $config['encoding'] ?? 'UTF-8';

        $lines = explode("\n", $contents);
        $headers = [];

        if ($hasHeader && !empty($lines)) {
            $headerLine = array_shift($lines);
            $headers = str_getcsv($headerLine, $delimiter);
        }

        return LazyCollection::make(function () use ($lines, $headers, $delimiter, $hasHeader) {
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $values = str_getcsv($line, $delimiter);

                if ($hasHeader && !empty($headers)) {
                    $record = [];
                    foreach ($headers as $index => $header) {
                        $record[$header] = $values[$index] ?? null;
                    }
                    yield $record;
                } else {
                    yield $values;
                }
            }
        });
    }

    private function parseJson(string $contents, array $config): LazyCollection
    {
        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('JSON inválido: ' . json_last_error_msg());
        }

        $dataPath = $config['data_path'] ?? null;
        $items = $dataPath ? data_get($data, $dataPath, $data) : $data;

        if (!is_array($items)) {
            $items = [$items];
        }

        if (isset($items[0]) && is_array($items[0])) {
            return LazyCollection::make(function () use ($items) {
                foreach ($items as $item) {
                    yield $item;
                }
            });
        }

        return LazyCollection::make(function () use ($items) {
            yield $items;
        });
    }

    private function parseExcel(array $config): LazyCollection
    {
        throw new \RuntimeException('Excel parsing requires maatwebsite/excel package. Use CSV or JSON instead.');
    }

    private function parseXml(string $contents): LazyCollection
    {
        $xml = simplexml_load_string($contents);

        if ($xml === false) {
            throw new \InvalidArgumentException('XML inválido');
        }

        $json = json_encode($xml);
        $data = json_decode($json, true);

        return LazyCollection::make(function () use ($data) {
            if (isset($data['row']) && is_array($data['row'])) {
                foreach ($data['row'] as $item) {
                    yield $item;
                }
            } else {
                yield $data;
            }
        });
    }
}
