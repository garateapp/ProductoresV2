<?php

namespace App\Services\Planning;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessProgressSqlsrvService
{
    private const VIEW = 'VW_MON_Partita_Corrente';

    public function currentByProcess(): array
    {
        try {
            $rows = DB::connection('unitec')->select(
                'SELECT ProcessoCodice as numero_proceso,
                        PartitaInizio as inicio,
                        UnitaPianificate as kilos_planificados,
                        UnitaSvuotate as kilos_vaciados,
                        UnitaSvuotatePerc as porcentaje_avance
                 FROM '.self::VIEW
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo cargar el avance de procesos Unitec: '.$e->getMessage());

            return [];
        }

        return collect($rows)
            ->map(fn ($row) => $this->normalizeRow($row))
            ->filter(fn (array $row) => $row['numero_proceso'] !== '')
            ->keyBy(fn (array $row) => self::normalizeProcessNumber($row['numero_proceso']))
            ->all();
    }

    public static function normalizeProcessNumber(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return ctype_digit($value) ? (string) (int) $value : $value;
    }

    private function normalizeRow(mixed $row): array
    {
        $get = function (string $key) use ($row): mixed {
            if (is_array($row)) {
                return $row[$key] ?? null;
            }

            return $row->{$key} ?? null;
        };

        return [
            'numero_proceso' => trim((string) ($get('numero_proceso') ?? '')),
            'inicio' => trim((string) ($get('inicio') ?? '')),
            'kilos_planificados' => $this->number($get('kilos_planificados')),
            'kilos_vaciados' => $this->number($get('kilos_vaciados')),
            'porcentaje_avance' => $this->number($get('porcentaje_avance')),
        ];
    }

    private function number(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $text = trim(str_replace(',', '.', (string) $value));

        return is_numeric($text) ? (float) $text : 0.0;
    }
}
