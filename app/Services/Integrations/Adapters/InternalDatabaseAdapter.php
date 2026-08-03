<?php

namespace App\Services\Integrations\Adapters;

use App\Contracts\Integrations\SourceAdapterInterface;
use Illuminate\Support\Facades\DB;

class InternalDatabaseAdapter implements SourceAdapterInterface
{
    public function key(): string
    {
        return 'internal_database';
    }

    public function label(): string
    {
        return 'Base de datos interna';
    }

    public function validateConfiguration(array $configuration): void
    {
        if (empty($configuration['table'])) {
            throw new \InvalidArgumentException('La configuración debe incluir una tabla origen');
        }
    }

    public function getSchema(array $configuration): array
    {
        $table = $configuration['table'] ?? null;
        if (!$table || !DB::getSchemaBuilder()->hasTable($table)) {
            return [];
        }

        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        $schema = [];

        foreach ($columns as $column) {
            $type = DB::getSchemaBuilder()->getColumnType($table, $column);
            $schema[] = [
                'name' => $column,
                'type' => $type,
            ];
        }

        return $schema;
    }

    public function count(array $configuration): int
    {
        $query = $this->buildQuery($configuration);
        return $query->count();
    }

    public function getRecords(array $configuration): \Generator
    {
        $query = $this->buildQuery($configuration);
        $chunkSize = $configuration['chunk_size'] ?? 500;

        $query->orderBy($configuration['key'] ?? 'id');

        foreach ($query->lazyById($chunkSize, $configuration['key'] ?? 'id') as $row) {
            yield $row->toArray();
        }
    }

    public function getStableIdentifier(array $record): string
    {
        return (string) ($record['id'] ?? '');
    }

    public function applyFilters(array $configuration, array $filters): array
    {
        return array_merge($configuration, [
            'filters' => $filters,
        ]);
    }

    public function getExamples(array $configuration): array
    {
        $query = $this->buildQuery($configuration);
        return $query->limit(5)->get()->toArray();
    }

    private function buildQuery(array $configuration)
    {
        $table = $configuration['table'] ?? null;
        if (!$table) {
            throw new \InvalidArgumentException('Tabla origen no configurada');
        }

        $columns = $configuration['columns'] ?? ['*'];
        $query = DB::table($table)->select($columns);

        if (!empty($configuration['filters'])) {
            foreach ($configuration['filters'] as $column => $value) {
                if (is_array($value)) {
                    $query->whereIn($column, $value);
                } else {
                    $query->where($column, $value);
                }
            }
        }

        if (!empty($configuration['where_raw'])) {
            $query->whereRaw($configuration['where_raw']);
        }

        if (!empty($configuration['date_from'])) {
            $query->where($configuration['date_column'] ?? 'created_at', '>=', $configuration['date_from']);
        }

        if (!empty($configuration['date_to'])) {
            $query->where($configuration['date_column'] ?? 'created_at', '<=', $configuration['date_to']);
        }

        return $query;
    }
}
