<?php

namespace App\Services;

use App\Enums\EstimationType;
use App\Enums\EstimationVersionStatus;
use App\Models\EstimationAudit;
use App\Models\EstimationRow;
use App\Models\EstimationStatus;
use App\Models\EstimationVersion;
use App\Models\EstimationWeek;
use App\Models\EstimationWeekValue;
use App\Models\User;
use App\Models\Variedad;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EstimationImportService
{
    public function importCsv(UploadedFile $file, array $meta, User $user): EstimationVersion
    {
        return $this->importCsvFromPath(
            $file->getRealPath(),
            $file->getClientOriginalName(),
            $meta,
            $user
        );
    }

    public function importCsvFromPath(string $absolutePath, string $originalName, array $meta, User $user, ?string $storedPath = null): EstimationVersion
    {
        $payload = validator($meta, [
            'season_id' => ['required', 'exists:estimation_seasons,id'],
            'type' => ['required', Rule::in(array_column(EstimationType::cases(), 'value'))],
            'period_start_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'period_end_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'source' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $parsed = $this->parseCsv($absolutePath);
        $rows = $parsed['rows'];
        $weekNumbers = $parsed['weeks'];
        $errors = $parsed['errors'];

        $seasonWeeks = EstimationWeek::where('season_id', $payload['season_id'])
            ->pluck('week_number')
            ->all();
        $missingWeeks = array_values(array_diff($weekNumbers, $seasonWeeks));
        if (! empty($missingWeeks)) {
            $errors[] = 'Semanas no registradas en la temporada: '.implode(', ', $missingWeeks);
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages(['file' => $errors]);
        }

        if (empty($rows)) {
            throw ValidationException::withMessages(['file' => ['No se encontraron filas validas para importar.']]);
        }

        return DB::transaction(function () use ($payload, $rows, $user, $originalName, $storedPath, $absolutePath): EstimationVersion {
            $filePath = $storedPath ?? $this->storeFileFromPath($absolutePath, $originalName);

            EstimationVersion::where('season_id', $payload['season_id'])
                ->where('type', $payload['type'])
                ->where('period_start_week', $payload['period_start_week'] ?? null)
                ->where('period_end_week', $payload['period_end_week'] ?? null)
                ->where('status', EstimationVersionStatus::ACTIVE->value)
                ->update(['status' => EstimationVersionStatus::SUPERSEDED->value]);

            $version = EstimationVersion::create([
                'season_id' => $payload['season_id'],
                'type' => $payload['type'],
                'period_start_week' => $payload['period_start_week'] ?? null,
                'period_end_week' => $payload['period_end_week'] ?? null,
                'source' => $payload['source'] ?? 'upload',
                'uploaded_by' => $user->id,
                'status' => EstimationVersionStatus::ACTIVE,
                'file_name' => $originalName,
                'file_path' => $filePath,
                'notes' => $payload['notes'] ?? null,
            ]);

            foreach ($rows as $rowData) {
                $row = EstimationRow::create(array_merge($rowData['row'], [
                    'estimation_version_id' => $version->id,
                ]));

                foreach ($rowData['weeks'] as $weekNumber => $kilos) {
                    EstimationWeekValue::create([
                        'estimation_row_id' => $row->id,
                        'week_number' => $weekNumber,
                        'kilos' => $kilos,
                    ]);
                }

                EstimationAudit::create([
                    'estimation_version_id' => $version->id,
                    'estimation_row_id' => $row->id,
                    'field_name' => 'row',
                    'action' => 'insert',
                    'source' => $payload['source'] ?? 'upload',
                    'old_value' => null,
                    'new_value' => json_encode([
                        'row' => $rowData['row'],
                        'weeks' => $rowData['weeks'],
                    ]),
                    'changed_by' => $user->id,
                    'changed_at' => now(),
                ]);
            }

            return $version;
        });
    }

    public function cloneVersion(EstimationVersion $base, User $user, string $source = 'manual'): EstimationVersion
    {
        return DB::transaction(function () use ($base, $user, $source): EstimationVersion {
            $base->load(['rows.weekValues']);

            EstimationVersion::where('season_id', $base->season_id)
                ->where('type', $base->type)
                ->where('period_start_week', $base->period_start_week)
                ->where('period_end_week', $base->period_end_week)
                ->where('status', EstimationVersionStatus::ACTIVE->value)
                ->update(['status' => EstimationVersionStatus::SUPERSEDED->value]);

            $version = EstimationVersion::create([
                'season_id' => $base->season_id,
                'type' => $base->type,
                'period_start_week' => $base->period_start_week,
                'period_end_week' => $base->period_end_week,
                'source' => $source,
                'uploaded_by' => $user->id,
                'status' => EstimationVersionStatus::ACTIVE,
                'notes' => $base->notes,
            ]);

            foreach ($base->rows as $row) {
                $newRow = $row->replicate();
                $newRow->estimation_version_id = $version->id;
                $newRow->created_at = null;
                $newRow->updated_at = null;
                $newRow->save();

                foreach ($row->weekValues as $weekValue) {
                    $newWeekValue = $weekValue->replicate();
                    $newWeekValue->estimation_row_id = $newRow->id;
                    $newWeekValue->created_at = null;
                    $newWeekValue->updated_at = null;
                    $newWeekValue->save();
                }

                EstimationAudit::create([
                    'estimation_version_id' => $version->id,
                    'estimation_row_id' => $newRow->id,
                    'field_name' => 'row',
                    'action' => 'clone',
                    'source' => $source,
                    'old_value' => null,
                    'new_value' => json_encode(['cloned_from' => $base->id]),
                    'changed_by' => $user->id,
                    'changed_at' => now(),
                ]);
            }

            return $version;
        });
    }

    public function applyManualUpdate(EstimationVersion $base, array $payload, User $user): EstimationVersion
    {
        $version = $this->cloneVersion($base, $user, 'manual');

        $baseRow = $base->rows()->findOrFail($payload['row_id']);
        $row = $version->rows()
            ->where('producer_id', $baseRow->producer_id)
            ->where('variedad_id', $baseRow->variedad_id)
            ->where('radio_mosca', $baseRow->radio_mosca)
            ->where('sucursal', $baseRow->sucursal)
            ->firstOrFail();

        $row->load('weekValues');
        $oldSnapshot = $this->snapshotRow($row);

        $row->fill($payload['row'] ?? []);
        $row->save();

        foreach ($payload['weeks'] ?? [] as $weekNumber => $kilos) {
            EstimationWeekValue::updateOrCreate(
                [
                    'estimation_row_id' => $row->id,
                    'week_number' => (int) $weekNumber,
                ],
                ['kilos' => (float) $kilos]
            );
        }

        $row->load('weekValues');
        $newSnapshot = $this->snapshotRow($row);

        EstimationAudit::create([
            'estimation_version_id' => $version->id,
            'estimation_row_id' => $row->id,
            'field_name' => 'row',
            'action' => 'update',
            'source' => 'manual',
            'old_value' => json_encode($oldSnapshot),
            'new_value' => json_encode($newSnapshot),
            'changed_by' => $user->id,
            'changed_at' => now(),
        ]);

        return $version;
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => ['No se pudo leer el archivo CSV.']]);
        }

        $rawHeader = fgetcsv($handle, 0, ';');
        if (! is_array($rawHeader)) {
            throw ValidationException::withMessages(['file' => ['El CSV no tiene encabezado valido.']]);
        }

        $header = array_map(fn ($value) => $this->normalizeHeader($value), $rawHeader);
        $headerCount = count($header);

        $weekColumns = [];
        $totalColumn = null;
        foreach ($header as $index => $column) {
            $week = $this->parseWeekHeader($column);
            if ($week !== null) {
                $weekColumns[$week] = $index;
            }
            if ($totalColumn === null && str_starts_with($column, 'TOTAL KILO')) {
                $totalColumn = $column;
            }
        }

        $required = ['RAZON SOCIAL', 'SUCURSAL', 'VARIEDAD', 'RADIO MOSCA', 'STATUS'];
        $missing = array_values(array_diff($required, $header));
        $errors = [];
        if (! empty($missing)) {
            $errors[] = 'Faltan columnas requeridas: '.implode(', ', $missing);
        }
        if (empty($weekColumns)) {
            $errors[] = 'No se encontraron columnas de semanas en el CSV.';
        }

        $rows = [];
        $line = 1;
        $seenKeys = [];
        $producerCache = [];
        $agronomistCache = [];
        $variedadCache = [];
        $statusCache = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $line++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $row = array_pad($row, $headerCount, null);
            $data = [];
            foreach ($header as $idx => $key) {
                $data[$key] = $row[$idx] ?? null;
            }

            $producerName = $this->normalizeText($data['RAZON SOCIAL'] ?? '');
            $sucursal = $this->normalizeText($data['SUCURSAL'] ?? '');
            $variedadName = $this->normalizeText($data['VARIEDAD'] ?? '');
            $statusRaw = trim((string) ($data['STATUS'] ?? ''));
            $statusName = $this->normalizeText($statusRaw);
            $radioMosca = $this->parseYesNo($data['RADIO MOSCA'] ?? null);

            if ($producerName === '' || $sucursal === '' || $variedadName === '' || $statusRaw === '') {
                $errors[] = "Fila {$line}: faltan PRODUCTOR, SUCURSAL, VARIEDAD o STATUS.";
                continue;
            }
            if ($radioMosca === null) {
                $errors[] = "Fila {$line}: RADIO MOSCA debe ser SI/NO.";
                continue;
            }

            $producer = $producerCache[$producerName] ?? null;
            if (! $producer) {
                $producer = User::role('Productor')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($producerName)])
                    ->first();
                $producerCache[$producerName] = $producer;
            }
            if (! $producer) {
                $errors[] = "Fila {$line}: productor no encontrado ({$producerName}).";
                continue;
            }

            $variedad = $variedadCache[$variedadName] ?? null;
            if (! $variedad) {
                $variedad = Variedad::whereRaw('LOWER(name) = ?', [mb_strtolower($variedadName)])->first();
                $variedadCache[$variedadName] = $variedad;
            }
            if (! $variedad) {
                $errors[] = "Fila {$line}: variedad no encontrada ({$variedadName}).";
                continue;
            }

            $status = null;
            if (is_numeric($statusRaw)) {
                $statusId = (int) $statusRaw;
                $cacheKey = 'id:'.$statusId;
                $status = $statusCache[$cacheKey] ?? null;
                if (! $status) {
                    $status = EstimationStatus::find($statusId);
                    $statusCache[$cacheKey] = $status;
                }
            } else {
                $cacheKey = 'name:'.$statusName;
                $status = $statusCache[$cacheKey] ?? null;
                if (! $status) {
                    $status = EstimationStatus::whereRaw('LOWER(name) = ?', [mb_strtolower($statusName)])->first();
                    $statusCache[$cacheKey] = $status;
                }
            }
            if (! $status) {
                $errors[] = "Fila {$line}: status no encontrado ({$statusRaw}).";
                continue;
            }

            $agronomistName = $this->normalizeText($data['JEFE TECNICO'] ?? '');
            $agronomist = null;
            if ($agronomistName !== '') {
                $agronomist = $agronomistCache[$agronomistName] ?? null;
                if (! $agronomist) {
                $agronomist = User::whereRaw('LOWER(name) = ?', [mb_strtolower($agronomistName)])
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Agronomo', 'Agrónomo']))
                    ->first();
                    $agronomistCache[$agronomistName] = $agronomist;
                }
            }

            $key = $producer->id.'|'.mb_strtolower($sucursal).'|'.$variedad->id.'|'.($radioMosca ? '1' : '0');
            if (isset($seenKeys[$key])) {
                $errors[] = "Fila {$line}: clave unica duplicada (PRODUCTOR+SUCURSAL+VARIEDAD+RADIO MOSCA).";
                continue;
            }
            $seenKeys[$key] = true;

            $weeks = [];
            $totalFromWeeks = 0.0;
            foreach ($weekColumns as $weekNumber => $index) {
                $raw = $row[$index] ?? null;
                $kilos = $this->parseNumber($raw);
                if ($kilos > 0) {
                    $weeks[$weekNumber] = $kilos;
                    $totalFromWeeks += $kilos;
                }
            }

            $totalKilo = $totalColumn ? $this->parseNumber($data[$totalColumn] ?? null) : 0.0;
            if ($totalKilo <= 0 && $totalFromWeeks > 0) {
                $totalKilo = $totalFromWeeks;
            }

            $rows[] = [
                'row' => [
                    'grupo' => $this->normalizeText($data['GRUPO'] ?? ''),
                    'tipo_productor' => $this->normalizeText($data['TIPO DE PRODUCTOR'] ?? ''),
                    'producer_id' => $producer->id,
                    'sucursal' => $sucursal,
                    'agronomist_id' => $agronomist?->id,
                    'status_id' => $status->id,
                    'variedad_id' => $variedad->id,
                    'acopio' => $this->parseYesNo($data['ACOPIO'] ?? null) ?? false,
                    'radio_mosca' => $radioMosca,
                    'corea_greenex' => $this->parseYesNo($data['COREA GREENEX'] ?? null),
                    'tipo_cereza' => $this->normalizeText($data['TIPO CEREZA'] ?? ''),
                    'total_kilo' => $totalKilo > 0 ? $totalKilo : null,
                ],
                'weeks' => $weeks,
            ];
        }

        fclose($handle);

        return [
            'rows' => $rows,
            'weeks' => array_keys($weekColumns),
            'errors' => $errors,
        ];
    }

    private function parseWeekHeader(string $column): ?int
    {
        if ($column === '') {
            return null;
        }
        if (is_numeric($column)) {
            return (int) $column;
        }
        if (preg_match('/^SEM\s*(\d{1,2})$/', $column, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function normalizeHeader($value): string
    {
        $s = is_string($value) ? $value : (string) $value;
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s ?? '');
        $s = str_replace('_', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);
        $s = mb_strtoupper($s);

        return $s;
    }

    private function normalizeText($value): string
    {
        $s = is_string($value) ? $value : (string) $value;
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s ?? '');
    }

    private function parseNumber($value): float
    {
        if ($value === null) {
            return 0.0;
        }
        $s = (string) $value;
        $s = str_replace(['.', ' '], ['', ''], $s);
        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function parseYesNo($value): ?bool
    {
        if ($value === null) {
            return null;
        }
        $s = mb_strtoupper(trim((string) $value));
        if ($s === 'SI' || $s === 'SÍ' || $s === 'YES' || $s === 'Y' || $s === '1') {
            return true;
        }
        if ($s === 'NO' || $s === 'N' || $s === '0') {
            return false;
        }

        return null;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function storeFileFromPath(string $absolutePath, string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'csv';
        $name = (string) Str::uuid().'.'.$extension;
        $stream = fopen($absolutePath, 'r');
        if (! $stream) {
            throw ValidationException::withMessages(['file' => ['No se pudo almacenar el archivo importado.']]);
        }

        Storage::put('estimations/'.$name, $stream);
        fclose($stream);

        return 'estimations/'.$name;
    }

    private function snapshotRow(EstimationRow $row): array
    {
        return [
                'row' => $row->only([
                    'grupo',
                    'tipo_productor',
                    'producer_id',
                    'sucursal',
                    'agronomist_id',
                    'status_id',
                    'variedad_id',
                'acopio',
                'radio_mosca',
                'corea_greenex',
                'tipo_cereza',
                'total_kilo',
            ]),
            'weeks' => $row->weekValues
                ->sortBy('week_number')
                ->mapWithKeys(fn ($week) => [$week->week_number => (float) $week->kilos])
                ->all(),
        ];
    }
}
