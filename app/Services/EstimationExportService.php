<?php

namespace App\Services;

use App\Models\EstimationSeason;
use App\Models\EstimationVersion;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EstimationExportService
{
    public function streamTemplate(EstimationSeason $season, ?EstimationVersion $version): StreamedResponse
    {
        $weeks = $season->weeks()->where('is_active', true)->orderBy('week_number')->get();

        $header = [
            'GRUPO',
            'TIPO DE PRODUCTOR',
            'STATUS',
            'JEFE TECNICO',
            'RAZON SOCIAL',
            'SUCURSAL',
            'ACOPIO',
            'RADIO MOSCA',
            'VARIEDAD',
            'COREA GREENEX',
            'TIPO CEREZA',
            'TOTAL KILO '.$this->normalizeSeasonCode($season->code),
        ];

        foreach ($weeks as $week) {
            $header[] = (string) $week->week_number;
        }

        $rows = [];
        if ($version) {
            $version->load([
                'rows.weekValues',
                'rows.producer',
                'rows.agronomist',
                'rows.status',
                'rows.variedad',
            ]);

            foreach ($version->rows as $row) {
                $weekValues = $row->weekValues->keyBy('week_number');
                $line = [
                    $row->grupo ?? '',
                    $row->tipo_productor ?? '',
                    $row->status?->id ?? '',
                    $row->agronomist?->name ?? '',
                    $row->producer?->name ?? '',
                    $row->sucursal ?? '',
                    $this->formatYesNo($row->acopio),
                    $this->formatYesNo($row->radio_mosca),
                    $row->variedad?->name ?? '',
                    $this->formatYesNo($row->corea_greenex),
                    $row->tipo_cereza ?? '',
                    $this->formatNumber($row->total_kilo),
                ];

                foreach ($weeks as $week) {
                    $value = $weekValues->get($week->week_number);
                    $line[] = $value ? $this->formatNumber($value->kilos) : '';
                }

                $rows[] = $line;
            }
        }

        $fileName = 'estimaciones_'.$season->code.'.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $handle = fopen('php://output', 'w');
            if (! $handle) {
                return;
            }
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $header, ';');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formatYesNo(?bool $value): string
    {
        if ($value === null) {
            return '';
        }

        return $value ? 'SI' : 'NO';
    }

    private function formatNumber($value): string
    {
        if ($value === null) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    private function normalizeSeasonCode(string $code): string
    {
        $trimmed = strtoupper(trim($code));
        if (str_starts_with($trimmed, 'T')) {
            $trimmed = substr($trimmed, 1);
        }

        return $trimmed;
    }
}
