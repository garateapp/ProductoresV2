<?php

namespace App\Services;

use App\Models\EstimationSeason;
use App\Models\EstimationVersion;
use App\Models\Recepcion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EstimationExportService
{
    public function streamTemplate(EstimationSeason $season, ?EstimationVersion $version, string $species): StreamedResponse
    {
        $weeks = $season->weeks()
            ->where('is_active', true)
            ->get()
            ->sortBy(function ($week) {
                $dateKey = $week->start_date ? $week->start_date->timestamp : PHP_INT_MAX;
                $weekNumber = $week->week_number ?? 0;

                return sprintf('%010d-%03d', $dateKey, $weekNumber);
            })
            ->values();

        $isCherrySpecies = $this->isCherrySpecies($species);

        $header = [
            'GRUPO',
            'TIPO DE PRODUCTOR',
            'STATUS',
            'JEFE TECNICO',
            'RAZON SOCIAL',
            'ACOPIO',
        ];

        if ($isCherrySpecies) {
            $header = array_merge($header, [
                'RADIO MOSCA',
                'VARIEDAD',
                'COREA GREENEX',
                'TIPO CEREZA',
            ]);
        } else {
            $header = array_merge($header, [
                'VARIEDAD',
                'VARIEDAD ROTULADA',
                'PLANTA',
                'MEXICO',
            ]);
        }

        $header[] = 'TOTAL KILO '.$this->normalizeSeasonCode($season->code);

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
                    $this->formatYesNo($row->acopio),
                ];

                if ($isCherrySpecies) {
                    $line = array_merge($line, [
                        $this->formatYesNo($row->radio_mosca),
                        $row->variedad?->name ?? '',
                        $this->formatYesNo($row->corea_greenex),
                        $row->tipo_cereza ?? '',
                    ]);
                } else {
                    $variedad = $row->variedad?->name ?? '';
                    $line = array_merge($line, [
                        $variedad,
                        $row->variedad_rotulada ?: $variedad,
                        $row->planta ?? '',
                        $this->formatYesNo($row->mexico),
                    ]);
                }

                $line[] = $this->formatNumber($row->total_kilo);

                foreach ($weeks as $week) {
                    $value = $weekValues->get($week->week_number);
                    $line[] = $value ? $this->formatNumber($value->kilos) : '';
                }

                $rows[] = $line;
            }
        }

        if (empty($rows)) {
            $rows = $this->buildPreviousSeasonReceptionRows($season, $weeks, $species);
        }
          if ($isCherrySpecies) {
        $fileName = 'estimaciones_'.$season->code.'_'.$species.'.csv';
          } else {
              $fileName = 'estimaciones_'.$season->code.'_carozos.csv';
          }

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

    private function buildPreviousSeasonReceptionRows(EstimationSeason $season, Collection $weeks, string $species): array
    {
        $previousSeason = EstimationSeason::query()
            ->where('id', '<', $season->id)
            ->orderByDesc('id')
            ->first();

        if (! $previousSeason) {
            return [];
        }

        $fromDate = $previousSeason->start_date?->toDateString() ?: $previousSeason->weeks()->min('start_date');
        $toDate = $previousSeason->end_date?->toDateString() ?: $previousSeason->weeks()->max('end_date');

        if (! $fromDate || ! $toDate) {
            return [];
        }

        $weekNumbers = $weeks
            ->pluck('week_number')
            ->map(fn ($week) => (int) $week)
            ->values()
            ->all();
        if (empty($weekNumbers)) {
            return [];
        }

        $weekSet = array_fill_keys($weekNumbers, true);
        $rowsByKey = [];

        $recepciones=Recepcion::query()
            ->where('temporada', 'actual')
            ->whereDate('fecha_g_recepcion', '>=', $fromDate)
            ->whereDate('fecha_g_recepcion', '<=', $toDate)
            ->whereNotNull('n_productor_rotulado')

            ->whereNotNull('n_variedad');
            if(strtolower($species) === 'cherries') {
                $recepciones=$recepciones->where('n_especie', $species);
            }
            else{
                $recepciones=$recepciones->where('n_especie', '!=', 'cherries');
            }
            $recepciones->select([
                'fecha_g_recepcion',
                'n_productor_rotulado',
                'n_variedad',
                'peso_neto',
            ])
            ->orderBy('fecha_g_recepcion')
            ->chunk(1000, function ($recepciones) use (&$rowsByKey, $weekNumbers, $weekSet) {
                foreach ($recepciones as $recepcion) {
                    $producer = trim((string) $recepcion->n_productor_rotulado);
                    $variedad = trim((string) $recepcion->n_variedad);

                    if ($producer === '' || $variedad === '') {
                        continue;
                    }

                    $kilos = (float) ($recepcion->peso_neto ?? 0);
                    if ($kilos <= 0) {
                        continue;
                    }

                    try {
                        $weekNumber = Carbon::parse($recepcion->fecha_g_recepcion)->isoWeek;
                    } catch (\Throwable $e) {
                        continue;
                    }

                    if (! isset($weekSet[$weekNumber])) {
                        continue;
                    }

                    $key = mb_strtolower($producer).'|'.mb_strtolower($variedad);
                    if (! isset($rowsByKey[$key])) {
                        $rowsByKey[$key] = [
                            'producer' => $producer,
                            'variedad' => $variedad,
                            'total' => 0.0,
                            'weeks' => array_fill_keys($weekNumbers, 0.0),
                        ];
                    }

                    $rowsByKey[$key]['weeks'][$weekNumber] += $kilos;
                    $rowsByKey[$key]['total'] += $kilos;
                }
            });

        if (empty($rowsByKey)) {
            return [];
        }

        $isCherrySpecies = $this->isCherrySpecies($species);

        uasort($rowsByKey, function (array $a, array $b): int {
            return [$a['producer'], $a['variedad']]
                <=>
                [$b['producer'], $b['variedad']];
        });

        $rows = [];
        foreach ($rowsByKey as $item) {
            $line = [
                '',
                'ANTIGUO',
                '',
                '',
                $item['producer'],
                '',
            ];

            if ($isCherrySpecies) {
                $line = array_merge($line, [
                    '',
                    $item['variedad'],
                    '',
                    '',
                ]);
            } else {
                $line = array_merge($line, [
                    $item['variedad'],
                    $item['variedad'],
                    '',
                    '',
                ]);
            }

            $line[] = $this->formatNumber($item['total']);

            foreach ($weekNumbers as $weekNumber) {
                $kilos = (float) ($item['weeks'][$weekNumber] ?? 0.0);
                $line[] = $kilos > 0 ? $this->formatNumber($kilos) : '';
            }

            $rows[] = $line;
        }

        return $rows;
    }

    private function isCherrySpecies(string $species): bool
    {
        return mb_strtolower(trim($species)) === 'cherries';
    }
}
