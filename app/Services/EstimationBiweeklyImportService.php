<?php

namespace App\Services;

use App\Enums\EstimationVersionStatus;
use App\Models\EstimationBiweeklyAudit;
use App\Models\EstimationBiweeklyRow;
use App\Models\EstimationBiweeklyVersion;
use App\Models\EstimationWeek;
use App\Models\Service;
use App\Models\User;
use App\Models\Variedad;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EstimationBiweeklyImportService
{
    public function importExcelFromPath(string $absolutePath, string $originalName, array $meta, User $user, ?string $storedPath = null): EstimationBiweeklyVersion
    {
        $payload = validator($meta, [
            'season_id' => ['required', 'exists:estimation_seasons,id'],
            'period_start_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'period_end_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'source' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $parsed = $this->parseExcel($absolutePath);
        $rows = $parsed['rows'];
        $weekNumbers = $parsed['weeks'];
        $errors = $parsed['errors'];

        if (! empty($weekNumbers)) {
            $seasonWeeks = EstimationWeek::where('season_id', $payload['season_id'])
                ->pluck('week_number')
                ->all();
            $missingWeeks = array_values(array_diff($weekNumbers, $seasonWeeks));
            if (! empty($missingWeeks)) {
                $errors[] = 'Semanas no registradas en la temporada: '.implode(', ', $missingWeeks);
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages(['file' => $errors]);
        }

        if (empty($rows)) {
            throw ValidationException::withMessages(['file' => ['No se encontraron filas validas para importar.']]);
        }

        $payload['origin'] = 'agronomo';
        $payload['period_start_week'] = $payload['period_start_week'] ?? (empty($weekNumbers) ? null : min($weekNumbers));
        $payload['period_end_week'] = $payload['period_end_week'] ?? (empty($weekNumbers) ? null : max($weekNumbers));

        return DB::transaction(function () use ($payload, $rows, $user, $originalName, $storedPath, $absolutePath): EstimationBiweeklyVersion {
            $filePath = $storedPath ?? $this->storeFileFromPath($absolutePath, $originalName);

            EstimationBiweeklyVersion::where('season_id', $payload['season_id'])
                ->where('origin', $payload['origin'])
                ->where('period_start_week', $payload['period_start_week'] ?? null)
                ->where('period_end_week', $payload['period_end_week'] ?? null)
                ->where('status', EstimationVersionStatus::ACTIVE->value)
                ->update(['status' => EstimationVersionStatus::SUPERSEDED->value]);

            $version = EstimationBiweeklyVersion::create([
                'season_id' => $payload['season_id'],
                'origin' => $payload['origin'],
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
                $row = EstimationBiweeklyRow::create(array_merge($rowData, [
                    'estimation_biweekly_version_id' => $version->id,
                ]));

                EstimationBiweeklyAudit::create([
                    'estimation_biweekly_version_id' => $version->id,
                    'estimation_biweekly_row_id' => $row->id,
                    'field_name' => 'row',
                    'action' => 'insert',
                    'source' => $payload['source'] ?? 'upload',
                    'old_value' => null,
                    'new_value' => json_encode($rowData),
                    'changed_by' => $user->id,
                    'changed_at' => now(),
                ]);
            }

            return $version;
        });
    }

    public function cloneVersion(EstimationBiweeklyVersion $base, User $user, string $source = 'manual'): EstimationBiweeklyVersion
    {
        return DB::transaction(function () use ($base, $user, $source): EstimationBiweeklyVersion {
            $base->load('rows');
            $origin = (string) ($base->origin ?: 'agronomo');

            EstimationBiweeklyVersion::where('season_id', $base->season_id)
                ->where('origin', $origin)
                ->where('period_start_week', $base->period_start_week)
                ->where('period_end_week', $base->period_end_week)
                ->where('status', EstimationVersionStatus::ACTIVE->value)
                ->update(['status' => EstimationVersionStatus::SUPERSEDED->value]);

            $version = EstimationBiweeklyVersion::create([
                'season_id' => $base->season_id,
                'origin' => $origin,
                'period_start_week' => $base->period_start_week,
                'period_end_week' => $base->period_end_week,
                'source' => $source,
                'uploaded_by' => $user->id,
                'status' => EstimationVersionStatus::ACTIVE,
                'notes' => $base->notes,
            ]);

            foreach ($base->rows as $row) {
                $newRow = $row->replicate();
                $newRow->estimation_biweekly_version_id = $version->id;
                $newRow->created_at = null;
                $newRow->updated_at = null;
                $newRow->save();

                EstimationBiweeklyAudit::create([
                    'estimation_biweekly_version_id' => $version->id,
                    'estimation_biweekly_row_id' => $newRow->id,
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

    public function applyManualUpdate(EstimationBiweeklyVersion $base, array $payload, User $user): EstimationBiweeklyVersion
    {
        $version = $this->cloneVersion($base, $user, 'manual');

        $baseRow = $base->rows()->findOrFail($payload['row_id']);
        $row = $version->rows()
            ->where('producer_id', $baseRow->producer_id)
            ->where('service_id', $baseRow->service_id)
            ->where('sucursal', $baseRow->sucursal)
            ->where('variedad_id', $baseRow->variedad_id)
            ->whereDate('dia', $baseRow->dia)
            ->where('total_kilo', $baseRow->total_kilo)
            ->firstOrFail();

        $oldSnapshot = $row->toArray();
        $row->fill($payload['row'] ?? []);
        $row->save();

        EstimationBiweeklyAudit::create([
            'estimation_biweekly_version_id' => $version->id,
            'estimation_biweekly_row_id' => $row->id,
            'field_name' => 'row',
            'action' => 'update',
            'source' => 'manual',
            'old_value' => json_encode($oldSnapshot),
            'new_value' => json_encode($row->toArray()),
            'changed_by' => $user->id,
            'changed_at' => now(),
        ]);

        return $version;
    }

    public function createManualServiceVersion(array $payload, User $user): EstimationBiweeklyVersion
    {
        $data = validator($payload, [
            'season_id' => ['required', 'exists:estimation_seasons,id'],
            'period_start_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'period_end_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'source' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.service_id' => ['required', 'exists:services,id'],
            'rows.*.variedad_id' => ['required', 'exists:variedads,id'],
            'rows.*.especie' => ['nullable', 'string', 'max:80'],
            'rows.*.planta' => ['nullable', 'string', 'max:120'],
            'rows.*.tipo' => ['nullable', 'string', 'max:80'],
            'rows.*.acopio' => ['nullable', 'boolean'],
            'rows.*.mexico' => ['nullable', 'boolean'],
            'rows.*.dia' => ['required', 'date'],
            'rows.*.total_kilo' => ['required', 'numeric', 'min:0.001'],
        ])->validate();

        $rows = collect($data['rows'])->values();
        $excludedServiceIds = [4, 6];

        $serviceIds = $rows
            ->pluck('service_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $services = Service::query()
            ->with(['owner:id,name,csg'])
            ->whereIn('id', $serviceIds)
            ->whereNotIn('id', $excludedServiceIds)
            ->get()
            ->keyBy('id');

        $invalidServices = array_values(array_diff($serviceIds, $services->keys()->map(fn ($id) => (int) $id)->all()));
        if (! empty($invalidServices)) {
            throw ValidationException::withMessages([
                'rows' => ['Servicios no permitidos o no encontrados: '.implode(', ', $invalidServices)],
            ]);
        }

        $servicesWithoutOwner = $services
            ->filter(fn (Service $service) => ! $service->owner_id)
            ->keys()
            ->values()
            ->all();
        if (! empty($servicesWithoutOwner)) {
            throw ValidationException::withMessages([
                'rows' => ['Todos los servicios deben tener dueño asignado. Servicios sin dueño: '.implode(', ', $servicesWithoutOwner)],
            ]);
        }

        $variedadIds = $rows
            ->pluck('variedad_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $variedades = Variedad::query()
            ->with(['especie:id,name'])
            ->whereIn('id', $variedadIds)
            ->get()
            ->keyBy('id');

        $missingVariedades = array_values(array_diff($variedadIds, $variedades->keys()->map(fn ($id) => (int) $id)->all()));
        if (! empty($missingVariedades)) {
            throw ValidationException::withMessages([
                'rows' => ['Variedades no encontradas: '.implode(', ', $missingVariedades)],
            ]);
        }

        $normalizedRows = [];
        $weekNumbers = [];
        $seenKeys = [];
        foreach ($rows as $index => $rowData) {
            $serviceId = (int) $rowData['service_id'];
            /** @var Service $service */
            $service = $services->get($serviceId);
            /** @var Variedad $variedad */
            $variedad = $variedades->get((int) $rowData['variedad_id']);
            $owner = $service?->owner;
            if (! $service || ! $variedad || ! $owner) {
                throw ValidationException::withMessages([
                    'rows' => ['Fila '.($index + 1).': no se pudo resolver servicio, variedad o dueño.'],
                ]);
            }

            $day = Carbon::parse((string) $rowData['dia'])->startOfDay();
            $week = (int) $day->isoWeek();
            $weekNumbers[] = $week;
            $totalKilo = (float) $rowData['total_kilo'];

            $duplicateKey = $serviceId
                .'|'.((int) $owner->id)
                .'|'.((int) $variedad->id)
                .'|'.$day->toDateString()
                .'|'.number_format($totalKilo, 3, '.', '');
            if (isset($seenKeys[$duplicateKey])) {
                throw ValidationException::withMessages([
                    'rows' => ['Fila '.($index + 1).': existe una fila duplicada para servicio + variedad + día + total kilo.'],
                ]);
            }
            $seenKeys[$duplicateKey] = true;

            $species = trim((string) ($variedad->especie?->name ?: ($rowData['especie'] ?? '')));
            if ($species === '') {
                throw ValidationException::withMessages([
                    'rows' => ['Fila '.($index + 1).': no se pudo resolver especie desde la variedad.'],
                ]);
            }

            $mexico = array_key_exists('mexico', $rowData)
                ? ($rowData['mexico'] === null ? null : (bool) $rowData['mexico'])
                : null;

            $normalizedRows[] = [
                'service_id' => $serviceId,
                'producer_id' => (int) $owner->id,
                'agronomist_id' => null,
                'variedad_id' => (int) $variedad->id,
                'planta' => trim((string) ($rowData['planta'] ?? $service->name)),
                'sucursal' => 'SERV-'.$serviceId,
                'csg' => trim((string) ($owner->csg ?? '')),
                'especie' => $species,
                'tipo' => trim((string) ($rowData['tipo'] ?? 'SERVICIO')),
                'acopio' => (bool) ($rowData['acopio'] ?? false),
                'mexico' => $mexico,
                'dia' => $day->toDateString(),
                'semana' => $week,
                'total_kilo' => $totalKilo,
            ];
        }

        $weekNumbers = array_values(array_unique($weekNumbers));
        if (! empty($weekNumbers)) {
            $seasonWeeks = EstimationWeek::query()
                ->where('season_id', $data['season_id'])
                ->pluck('week_number')
                ->all();
            $missingWeeks = array_values(array_diff($weekNumbers, $seasonWeeks));
            if (! empty($missingWeeks)) {
                throw ValidationException::withMessages([
                    'rows' => ['Semanas no registradas en la temporada: '.implode(', ', $missingWeeks)],
                ]);
            }
        }

        $periodStartWeek = $data['period_start_week'] ?? (empty($weekNumbers) ? null : min($weekNumbers));
        $periodEndWeek = $data['period_end_week'] ?? (empty($weekNumbers) ? null : max($weekNumbers));
        if ($periodStartWeek !== null && $periodEndWeek !== null && $periodStartWeek > $periodEndWeek) {
            throw ValidationException::withMessages([
                'period_start_week' => ['La semana de inicio no puede ser mayor a la semana de fin.'],
            ]);
        }
        if ($periodStartWeek !== null && $periodEndWeek !== null) {
            $outsideWeeks = array_values(array_filter($weekNumbers, fn ($week) => $week < $periodStartWeek || $week > $periodEndWeek));
            if (! empty($outsideWeeks)) {
                throw ValidationException::withMessages([
                    'rows' => ['Hay filas fuera del rango de semanas definido: '.implode(', ', $outsideWeeks)],
                ]);
            }
        }

        $origin = 'servicio_planificador';
        $source = $data['source'] ?? 'planner_manual';

        return DB::transaction(function () use ($data, $normalizedRows, $periodStartWeek, $periodEndWeek, $origin, $source, $user): EstimationBiweeklyVersion {
            EstimationBiweeklyVersion::query()
                ->where('season_id', $data['season_id'])
                ->where('origin', $origin)
                ->where('period_start_week', $periodStartWeek)
                ->where('period_end_week', $periodEndWeek)
                ->where('status', EstimationVersionStatus::ACTIVE->value)
                ->update(['status' => EstimationVersionStatus::SUPERSEDED->value]);

            $version = EstimationBiweeklyVersion::query()->create([
                'season_id' => $data['season_id'],
                'origin' => $origin,
                'period_start_week' => $periodStartWeek,
                'period_end_week' => $periodEndWeek,
                'source' => $source,
                'uploaded_by' => $user->id,
                'status' => EstimationVersionStatus::ACTIVE,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($normalizedRows as $rowData) {
                $row = EstimationBiweeklyRow::query()->create([
                    ...$rowData,
                    'estimation_biweekly_version_id' => $version->id,
                ]);

                EstimationBiweeklyAudit::query()->create([
                    'estimation_biweekly_version_id' => $version->id,
                    'estimation_biweekly_row_id' => $row->id,
                    'field_name' => 'row',
                    'action' => 'insert',
                    'source' => $source,
                    'old_value' => null,
                    'new_value' => json_encode($rowData),
                    'changed_by' => $user->id,
                    'changed_at' => now(),
                ]);
            }

            return $version;
        });
    }

    private function parseExcel(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file' => ['No se pudo leer el archivo Excel.']]);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        if (empty($rows)) {
            throw ValidationException::withMessages(['file' => ['El Excel no tiene filas.']]);
        }

        $headerRow = array_shift($rows);
        $columns = [];
        foreach ($headerRow as $col => $value) {
            $columns[$col] = $this->normalizeHeader($value);
        }

        $required = [
            'JEFE TECNICO',
            'PLANTA',
            'ACOPIO',
            'RAZON SOCIAL',
            'SUCURSAL',
            'CSG',
            'ESPECIE',
            'TIPO',
            'MEXICO',
            'VARIEDAD',
            'DIA',
            'SEMANA',
            'TOTAL KILO',
        ];
        $missing = array_values(array_diff($required, array_values($columns)));
        $errors = [];
        if (! empty($missing)) {
            $errors[] = 'Faltan columnas requeridas: '.implode(', ', $missing);
        }

        $rowsData = [];
        $line = 1;
        $seenKeys = [];
        $producerCache = [];
        $agronomistCache = [];
        $variedadCache = [];
        $weeks = [];

        foreach ($rows as $row) {
            $line++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $data = [];
            foreach ($columns as $col => $key) {
                $data[$key] = $row[$col] ?? null;
            }

            $producerName = $this->normalizeText($data['RAZON SOCIAL'] ?? '');
            $sucursal = $this->normalizeText($data['SUCURSAL'] ?? '');
            $variedadName = $this->normalizeText($data['VARIEDAD'] ?? '');
            $dia = $this->parseDate($data['DIA'] ?? null);
            $semana = $this->parseInt($data['SEMANA'] ?? null);
            $totalKilo = $this->parseNumber($data['TOTAL KILO'] ?? null);

            if ($producerName === '' || $variedadName === '' || ! $dia || ! $semana) {
                $errors[] = "Fila {$line}: faltan PRODUCTOR, VARIEDAD, DIA o SEMANA.";
                continue;
            }
            if ($totalKilo <= 0) {
                $errors[] = "Fila {$line}: TOTAL KILO requerido.";
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

            $key = $producer->id.'|'.mb_strtolower($sucursal).'|'.$variedad->id.'|'.$dia->format('Y-m-d').'|'.$totalKilo;
            if (isset($seenKeys[$key])) {
                $errors[] = "Fila {$line}: clave unica duplicada (PRODUCTOR+SUCURSAL+VARIEDAD+FECHA+TOTAL KILO).";
                continue;
            }
            $seenKeys[$key] = true;
            $weeks[] = $semana;

            $rowsData[] = [
                'producer_id' => $producer->id,
                'agronomist_id' => $agronomist?->id,
                'variedad_id' => $variedad->id,
                'planta' => $this->normalizeText($data['PLANTA'] ?? ''),
                'sucursal' => $sucursal,
                'csg' => $this->normalizeText($data['CSG'] ?? ''),
                'especie' => $this->normalizeText($data['ESPECIE'] ?? ''),
                'tipo' => $this->normalizeText($data['TIPO'] ?? ''),
                'acopio' => $this->parseYesNo($data['ACOPIO'] ?? null) ?? false,
                'mexico' => $this->parseYesNo($data['MEXICO'] ?? null),
                'dia' => $dia->format('Y-m-d'),
                'semana' => $semana,
                'total_kilo' => $totalKilo,
            ];
        }

        return [
            'rows' => $rowsData,
            'weeks' => array_values(array_unique($weeks)),
            'errors' => $errors,
        ];
    }

    private function normalizeHeader($value): string
    {
        $s = is_string($value) ? $value : (string) $value;
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s ?? '');
        $s = str_replace('_', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);
        $s = mb_strtoupper($s);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^A-Z0-9\s]/', '', $s);

        return trim($s);
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

    private function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', $text)) {
            [$first, $second, $year] = array_map('intval', explode('/', $text));
            $format = $first > 12 ? 'd/m/Y' : 'm/d/Y';
            $parsed = Carbon::createFromFormat($format, $text);

            return $parsed ?: Carbon::parse($text);
        }

        return Carbon::parse($text);
    }

    private function parseInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = trim((string) $value);

        return is_numeric($s) ? (int) $s : null;
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
        $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'xlsx';
        $name = (string) Str::uuid().'.'.$extension;
        $stream = fopen($absolutePath, 'r');
        if (! $stream) {
            throw ValidationException::withMessages(['file' => ['No se pudo almacenar el archivo importado.']]);
        }

        Storage::put('estimations/biweekly/'.$name, $stream);
        fclose($stream);

        return 'estimations/biweekly/'.$name;
    }
}
