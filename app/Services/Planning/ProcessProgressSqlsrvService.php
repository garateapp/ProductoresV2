<?php

namespace App\Services\Planning;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessProgressSqlsrvService
{
    private const VIEW = 'VW_MON_Partita_Corrente';
    private const PRODUCTIVITY_VIEW = 'VW_MON_Produttivita_Uscita_Live';

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

    public function kgPerHourByLine(): array
    {
        try {
            $rows = DB::connection('unitec')->select(
                'SELECT LineaCodice as linea_codigo,
                        LineaDescrizione as linea,
                        TurnoDescrizione as turno,
                        AVG(ChilogrammiOra) as [kg_hora]
                 FROM [UNITEC_DB_UNIREPORT].[dbo].'.self::PRODUCTIVITY_VIEW.'
                 WHERE FlussoSogliaPosizione = 1
                 GROUP BY LineaCodice, LineaDescrizione, TurnoDescrizione
                 ORDER BY LineaCodice'
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo cargar la velocidad Unitec: '.$e->getMessage());

            return [];
        }

        return collect($rows)
            ->map(fn ($row): array => [
                'linea_codigo' => trim((string) ($row->linea_codigo ?? '')),
                'linea' => trim((string) ($row->linea ?? '')),
                'turno' => trim((string) ($row->turno ?? '')),
                'kg_hora' => $this->number($row->kg_hora ?? null),
            ])
            ->filter(fn (array $row) => $row['linea_codigo'] !== '')
            ->values()
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
