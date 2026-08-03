<?php

namespace App\Services\Integrations\Adapters\Handlers;

use App\Contracts\Integrations\SourceAdapterHandler;
use Illuminate\Support\Facades\DB;

class DatabaseHandler implements SourceAdapterHandler
{
    public function validateConfiguration(array $config): void
    {
        if (empty($config['connection']) && empty($config['table'])) {
            throw new \InvalidArgumentException('La configuración debe incluir connection y table');
        }
    }

    public function getSchema(array $config): array
    {
        $table = $config['table'] ?? null;
        $connection = $config['connection'] ?? config('database.default');

        if (!$table || !DB::connection($connection)->getSchemaBuilder()->hasTable($table)) {
            return [];
        }

        $columns = DB::connection($connection)->getSchemaBuilder()->getColumnListing($table);
        $schema = [];

        foreach ($columns as $column) {
            $type = DB::connection($connection)->getSchemaBuilder()->getColumnType($table, $column);
            $schema[] = [
                'name' => $column,
                'type' => $type,
            ];
        }

        return $schema;
    }

    public function count(array $config): int
    {
        $query = $this->buildQuery($config);
        return $query->count();
    }

    public function getRecords(array $config): \Generator
    {
        $query = $this->buildQuery($config);
        $chunkSize = $config['chunk_size'] ?? 500;
        $key = $config['key'] ?? 'id';

        $query->orderBy($key);

        foreach ($query->lazyById($chunkSize, $key) as $row) {
            yield $row->toArray();
        }
    }

    public function getExamples(array $config): array
    {
        $query = $this->buildQuery($config);
        return $query->limit(5)->get()->toArray();
    }

    public function getStableIdentifier(array $record): string
    {
        return (string) ($record['id'] ?? '');
    }

    private function buildQuery(array $config)
    {
        $connection = $config['connection'] ?? config('database.default');
        $table = $config['table'] ?? null;

        if (!$table) {
            throw new \InvalidArgumentException('Tabla origen no configurada');
        }

        $columns = $config['columns'] ?? ['*'];
        $query = DB::connection($connection)->table($table)->select($columns);

        if (!empty($config['filters'])) {
            foreach ($config['filters'] as $column => $value) {
                if (is_array($value)) {
                    $query->whereIn($column, $value);
                } else {
                    $query->where($column, $value);
                }
            }
        }

        if (!empty($config['where_raw'])) {
            $query->whereRaw($config['where_raw']);
        }

        if (!empty($config['date_from'])) {
            $query->where($config['date_column'] ?? 'created_at', '>=', $config['date_from']);
        }

        if (!empty($config['date_to'])) {
            $query->where($config['date_column'] ?? 'created_at', '<=', $config['date_to']);
        }

        return $query;
    }
}
