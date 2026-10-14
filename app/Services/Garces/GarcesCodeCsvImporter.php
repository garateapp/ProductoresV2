<?php

namespace App\Services\Garces;

use App\Models\GarcesCodeMapping;

class GarcesCodeCsvImporter
{
    /**
     * Columnas aceptadas (alias => campo canónico).
     */
    private const COLUMN_ALIASES = [
        'tipo' => 'tipo',
        'type' => 'tipo',
        'clave_interna' => 'clave_interna',
        'clave interna' => 'clave_interna',
        'interna' => 'clave_interna',
        'valor_interno' => 'clave_interna',
        'clave_garces' => 'clave_garces',
        'clave garces' => 'clave_garces',
        'garces' => 'clave_garces',
        'valor_externo' => 'clave_garces',
        'clave_externa' => 'clave_garces',
        'vigencia_desde' => 'vigencia_desde',
        'desde' => 'vigencia_desde',
        'vigencia_hasta' => 'vigencia_hasta',
        'hasta' => 'vigencia_hasta',
        'prioridad' => 'prioridad',
        'activo' => 'activo',
        'notas' => 'notas',
    ];

    /**
     * @return array<int,array<string,mixed>>
     */
    public function parse(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $rows = [];
        $columns = null;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);

            if ($columns === null) {
                $columns = $this->detectColumns($cells);

                if ($columns !== null) {
                    continue;
                }

                $columns = ['tipo', 'clave_interna', 'clave_garces', 'vigencia_desde', 'vigencia_hasta', 'prioridad', 'activo', 'notas'];
            }

            $row = [];

            foreach ($columns as $index => $field) {
                if ($field === null) {
                    continue;
                }

                $row[$field] = isset($cells[$index]) ? trim((string) $cells[$index]) : null;
            }

            if (($row['tipo'] ?? '') === '' || ($row['clave_interna'] ?? '') === '' || ($row['clave_garces'] ?? '') === '') {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array{created:int,updated:int,skipped:int}
     */
    public function import(string $csv, ?int $userId = null): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($this->parse($csv) as $row) {
            $mapping = GarcesCodeMapping::query()
                ->where('tipo', $row['tipo'])
                ->where('clave_interna', $row['clave_interna'])
                ->where('clave_garces', $row['clave_garces'])
                ->first();

            $attributes = [
                'vigencia_desde' => $this->nullableDate($row['vigencia_desde'] ?? null),
                'vigencia_hasta' => $this->nullableDate($row['vigencia_hasta'] ?? null),
                'prioridad' => $this->toInt($row['prioridad'] ?? null, 0),
                'activo' => $this->toBool($row['activo'] ?? null, true),
                'notas' => ($row['notas'] ?? '') !== '' ? $row['notas'] : null,
                'updated_by' => $userId,
            ];

            if ($mapping) {
                $mapping->update($attributes);
                $updated++;

                continue;
            }

            GarcesCodeMapping::create(array_merge($attributes, [
                'tipo' => $row['tipo'],
                'clave_interna' => $row['clave_interna'],
                'clave_garces' => $row['clave_garces'],
                'created_by' => $userId,
            ]));

            $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $cells
     * @return array<int,?string>|null Mapa índice => campo, o null si no es cabecera.
     */
    private function detectColumns(array $cells): ?array
    {
        $known = 0;
        $columns = [];

        foreach ($cells as $cell) {
            $normalized = strtolower(trim((string) $cell));
            $field = self::COLUMN_ALIASES[$normalized] ?? null;

            if ($field !== null) {
                $known++;
            }

            $columns[] = $field;
        }

        return $known >= 2 ? $columns : null;
    }

    private function nullableDate(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function toInt(?string $value, int $default): int
    {
        $value = trim((string) $value);

        return $value !== '' && is_numeric($value) ? (int) $value : $default;
    }

    private function toBool(?string $value, bool $default): bool
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return $default;
        }

        return in_array($value, ['1', 'true', 'si', 'sí', 'yes', 'activo', 'x'], true);
    }
}
