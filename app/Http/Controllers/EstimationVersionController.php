<?php

namespace App\Http\Controllers;

use App\Enums\EstimationVersionStatus;
use App\Http\Controllers\Concerns\EnsuresEstimationsAccess;
use App\Http\Resources\EstimationVersionResource;
use App\Jobs\ImportEstimationsJob;
use App\Models\EstimationSeason;
use App\Models\EstimationStatus;
use App\Models\EstimationRow;
use App\Models\EstimationType;
use App\Models\EstimationVersion;
use App\Services\EstimationExportService;
use App\Services\EstimationImportService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Throwable;

class EstimationVersionController extends Controller
{
    use EnsuresEstimationsAccess;

    public function index(Request $request): Responsable
    {
        $this->ensureEstimationsAccess($request);

        $versions = EstimationVersion::query()
            ->with(['season', 'uploader'])
            ->withCount('rows')
            ->when($request->filled('season_id'), fn ($q) => $q->where('season_id', $request->season_id))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('species'), function ($query) use ($request) {
                $species = $this->normalizeSpeciesGroup((string) $request->input('species'));

                $query->where(function ($nestedQuery) use ($species) {
                    $nestedQuery->where('species', $species);

                    // Backward compatibility: old records without species were cherries.
                    if ($species === 'cherries') {
                        $nestedQuery->orWhereNull('species');
                    }
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(15);

        if ($request->expectsJson()) {
            return EstimationVersionResource::collection($versions);
        }

        $seasons = EstimationSeason::orderByDesc('id')->get(['id', 'code', 'name']);
        $statuses = EstimationStatus::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $types = EstimationType::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name', 'is_active']);

        return Inertia::render('Estimations/Index', [
            'versions' => EstimationVersionResource::collection($versions),
            'seasons' => $seasons,
            'statuses' => $statuses,
            'types' => $types,
            'filters' => $request->only(['season_id', 'type', 'species', 'status']),
        ]);
    }

    public function show(Request $request, EstimationVersion $estimation_version): Responsable
    {
        $this->ensureEstimationsAccess($request);

        $estimation_version->load(['season.weeks', 'uploader']);
        $weeks = $estimation_version->season?->weeks?->sortBy(function ($week) {
            $dateKey = $week->start_date ? $week->start_date->timestamp : PHP_INT_MAX;
            $weekNumber = $week->week_number ?? 0;

            return sprintf('%010d-%02d', $dateKey, $weekNumber);
        })->values() ?? collect();

        $rows = EstimationRow::query()
            ->where('estimation_version_id', $estimation_version->id)
            ->with(['producer', 'agronomist', 'status', 'variedad', 'weekValues'])
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'grupo' => $row->grupo,
                    'tipo_productor' => $row->tipo_productor,
                    'producer_id' => $row->producer_id,
                    'producer' => $row->producer?->name,
                    'agronomist_id' => $row->agronomist_id,
                    'agronomist' => $row->agronomist?->name,
                    'status_id' => $row->status_id,
                    'status' => $row->status?->name,
                    'variedad_id' => $row->variedad_id,
                    'variedad' => $row->variedad?->name,
                    'variedad_rotulada' => $row->variedad_rotulada,
                    'planta' => $row->planta,
                    'mexico' => $row->mexico,
                    'acopio' => (bool) $row->acopio,
                    'radio_mosca' => (bool) $row->radio_mosca,
                    'corea_greenex' => $row->corea_greenex,
                    'tipo_cereza' => $row->tipo_cereza,
                    'total_kilo' => $row->total_kilo,
                    'week_values' => $row->weekValues
                        ->mapWithKeys(fn ($value) => [(string) $value->week_number => (float) $value->kilos]),
                ];
            })
            ->values();

        $species = $this->resolveVersionSpecies($estimation_version);

        return Inertia::render('Estimations/Show', [
            'version' => [
                'id' => $estimation_version->id,
                'season' => [
                    'id' => $estimation_version->season?->id,
                    'code' => $estimation_version->season?->code,
                    'name' => $estimation_version->season?->name,
                ],
                'type' => $estimation_version->type,
                'species' => $species,
                'period_start_week' => $estimation_version->period_start_week,
                'period_end_week' => $estimation_version->period_end_week,
                'status' => $estimation_version->status?->value ?? $estimation_version->status,
                'source' => $estimation_version->source,
                'uploader' => $estimation_version->uploader?->name,
                'created_at' => optional($estimation_version->created_at)->toDateTimeString(),
            ],
            'weeks' => $weeks->map(fn ($week) => [
                'week_number' => $week->week_number,
                'start_date' => optional($week->start_date)->toDateString(),
                'end_date' => optional($week->end_date)->toDateString(),
                'is_active' => (bool) $week->is_active,
            ]),
            'rows' => $rows,
        ]);
    }

    public function upload(Request $request): Response
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
            'season_id' => ['required', 'exists:estimation_seasons,id'],
            'type' => [
                'required',
                'string',
                'max:32',
                Rule::exists('estimation_types', 'code')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'species' => ['required', Rule::in(['cherries', 'carozos', 'plums', 'nectarines', 'peaches'])],
            'period_start_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'period_end_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'source' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['species'] = $this->normalizeSpeciesGroup((string) $data['species']);
        unset($data['file']);

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return response(['message' => 'Archivo invalido'], 422);
        }

        try {
            $extension = $file->getClientOriginalExtension() ?: 'csv';
            $storedPath = Storage::putFileAs('estimations', $file, (string) Str::uuid().'.'.$extension);
            if (! $storedPath) {
                throw new RuntimeException('No se pudo almacenar el archivo de estimaciones.');
            }

            $absolutePath = Storage::path($storedPath);

            ImportEstimationsJob::dispatch(
                $absolutePath,
                $storedPath,
                $file->getClientOriginalName(),
                $data,
                $request->user()->id
            );
        } catch (Throwable $exception) {
            Log::error('No se pudo iniciar la importacion de estimaciones.', [
                'user_id' => $request->user()?->id,
                'season_id' => $data['season_id'] ?? null,
                'type' => $data['type'] ?? null,
                'file_name' => $file->getClientOriginalName(),
                'exception' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No se pudo iniciar la importacion. Intente nuevamente.',
                ], 500);
            }

            return back()->with('error', 'No se pudo iniciar la importacion. Intente nuevamente.');
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'queued'], 202);
        }

        return back()->with('success', 'Importacion en cola.');
    }

    public function clone(Request $request, EstimationVersion $estimation_version, EstimationImportService $importService): Response
    {
        $this->ensureEstimationsAccess($request);

        $version = $importService->cloneVersion($estimation_version, $request->user(), 'manual');

        return response([
            'status' => 'ok',
            'version_id' => $version->id,
        ]);
    }

    public function downloadVersion(Request $request, EstimationVersion $estimation_version, EstimationExportService $exportService): StreamedResponse
    {
        $this->ensureEstimationsAccess($request);

        $estimation_version->loadMissing('season');
        $species = $this->resolveVersionSpecies($estimation_version);
        $season = $estimation_version->season ?? EstimationSeason::findOrFail($estimation_version->season_id);

        return $exportService->streamTemplate(
            $season,
            $estimation_version,
            $species
        );
    }

    public function downloadTemplate(Request $request, EstimationExportService $exportService): StreamedResponse
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'season_id' => ['required', 'exists:estimation_seasons,id'],
            'type' => [
                'required',
                'string',
                'max:32',
                Rule::exists('estimation_types', 'code')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'species' => ['required', Rule::in(['cherries', 'plums', 'nectarines', 'peaches'])],
            'period_start_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'period_end_week' => ['nullable', 'integer', 'min:1', 'max:53'],
        ]);

        $season = EstimationSeason::findOrFail($data['season_id']);
        $speciesGroup = $this->normalizeSpeciesGroup((string) $data['species']);
        $version = EstimationVersion::query()
            ->where('season_id', $data['season_id'])
            ->where('type', $data['type'])
            ->where(function ($query) use ($speciesGroup) {
                $query->where('species', $speciesGroup)
                    ->orWhereNull('species');
            })
            ->where('period_start_week', $data['period_start_week'] ?? null)
            ->where('period_end_week', $data['period_end_week'] ?? null)
            ->where('status', EstimationVersionStatus::ACTIVE->value)
            ->first();

        return $exportService->streamTemplate($season, $version, (string) $data['species']);
    }

    private function normalizeSpeciesGroup(string $species): string
    {
        return mb_strtolower(trim($species)) === 'cherries'
            ? 'cherries'
            : 'carozos';
    }

    private function resolveVersionSpecies(EstimationVersion $version): string
    {
        if ($version->species) {
            return $this->normalizeSpeciesGroup((string) $version->species);
        }

        $inferred = $this->inferSpeciesFromRows($version)
            ?? $this->inferSpeciesFromCsvHeader($version)
            ?? 'cherries';

        if (Schema::hasColumn('estimation_versions', 'species')) {
            try {
                $version->forceFill(['species' => $inferred])->save();
            } catch (Throwable $exception) {
                Log::warning('No se pudo persistir la especie inferida para la version de estimacion.', [
                    'version_id' => $version->id,
                    'species' => $inferred,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $inferred;
    }

    private function inferSpeciesFromRows(EstimationVersion $version): ?string
    {
        if (
            ! Schema::hasColumn('estimation_rows', 'variedad_rotulada') ||
            ! Schema::hasColumn('estimation_rows', 'planta') ||
            ! Schema::hasColumn('estimation_rows', 'mexico')
        ) {
            return null;
        }

        $hasCarozoSignals = EstimationRow::query()
            ->where('estimation_version_id', $version->id)
            ->where(function ($query) {
                $query
                    ->whereNotNull('variedad_rotulada')
                    ->orWhereNotNull('planta')
                    ->orWhereNotNull('mexico');
            })
            ->exists();

        return $hasCarozoSignals ? 'carozos' : null;
    }

    private function inferSpeciesFromCsvHeader(EstimationVersion $version): ?string
    {
        if (! $version->file_path || ! Storage::exists($version->file_path)) {
            return null;
        }

        $handle = fopen(Storage::path($version->file_path), 'r');
        if (! $handle) {
            return null;
        }

        try {
            $rawHeader = fgetcsv($handle, 0, ';');
            if (! is_array($rawHeader)) {
                return null;
            }

            $header = array_map(fn ($value) => $this->normalizeCsvHeader((string) $value), $rawHeader);

            $hasCherrySignals = in_array('RADIO MOSCA', $header, true)
                || in_array('COREA GREENEX', $header, true)
                || in_array('TIPO CEREZA', $header, true);
            $hasCarozoSignals = in_array('VARIEDAD ROTULADA', $header, true)
                || in_array('PLANTA', $header, true)
                || in_array('MEXICO', $header, true);

            if ($hasCherrySignals && ! $hasCarozoSignals) {
                return 'cherries';
            }

            if ($hasCarozoSignals && ! $hasCherrySignals) {
                return 'carozos';
            }

            return null;
        } finally {
            fclose($handle);
        }
    }

    private function normalizeCsvHeader(string $value): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $header = str_replace('_', ' ', $header);
        $header = preg_replace('/\s+/', ' ', $header ?? '');

        return mb_strtoupper(trim($header ?? ''));
    }
}
