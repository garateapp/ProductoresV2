<?php

namespace App\Http\Controllers;

use App\Enums\EstimationVersionStatus;
use App\Http\Controllers\Concerns\EnsuresEstimationsAccess;
use App\Http\Resources\EstimationVersionResource;
use App\Jobs\ImportEstimationsJob;
use App\Models\EstimationSeason;
use App\Models\EstimationStatus;
use App\Models\EstimationRow;
use App\Models\EstimationVersion;
use App\Services\EstimationExportService;
use App\Services\EstimationImportService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;

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
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(15);

        if ($request->expectsJson()) {
            return EstimationVersionResource::collection($versions);
        }

        $seasons = EstimationSeason::orderByDesc('id')->get(['id', 'code', 'name']);
        $statuses = EstimationStatus::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Estimations/Index', [
            'versions' => EstimationVersionResource::collection($versions),
            'seasons' => $seasons,
            'statuses' => $statuses,
            'filters' => $request->only(['season_id', 'type', 'status']),
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
            ->paginate(25)
            ->through(function ($row) {
                return [
                    'id' => $row->id,
                    'grupo' => $row->grupo,
                    'tipo_productor' => $row->tipo_productor,
                    'producer_id' => $row->producer_id,
                    'producer' => $row->producer?->name,
                    'sucursal' => $row->sucursal,
                    'agronomist_id' => $row->agronomist_id,
                    'agronomist' => $row->agronomist?->name,
                    'status_id' => $row->status_id,
                    'status' => $row->status?->name,
                    'variedad_id' => $row->variedad_id,
                    'variedad' => $row->variedad?->name,
                    'acopio' => (bool) $row->acopio,
                    'radio_mosca' => (bool) $row->radio_mosca,
                    'corea_greenex' => $row->corea_greenex,
                    'tipo_cereza' => $row->tipo_cereza,
                    'total_kilo' => $row->total_kilo,
                    'week_values' => $row->weekValues
                        ->mapWithKeys(fn ($value) => [(string) $value->week_number => (float) $value->kilos]),
                ];
            });

        return Inertia::render('Estimations/Show', [
            'version' => [
                'id' => $estimation_version->id,
                'season' => [
                    'id' => $estimation_version->season?->id,
                    'code' => $estimation_version->season?->code,
                    'name' => $estimation_version->season?->name,
                ],
                'type' => $estimation_version->type?->value ?? $estimation_version->type,
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
            'type' => ['required', 'string', 'max:32'],
            'period_start_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'period_end_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'source' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ]);
        unset($data['file']);

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return response(['message' => 'Archivo invalido'], 422);
        }

        $extension = $file->getClientOriginalExtension() ?: 'csv';
        $storedPath = Storage::putFileAs('estimations', $file, (string) Str::uuid().'.'.$extension);
        $absolutePath = Storage::path($storedPath);

        ImportEstimationsJob::dispatch(
            $absolutePath,
            $storedPath,
            $file->getClientOriginalName(),
            $data,
            $request->user()->id
        );

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

    public function downloadTemplate(Request $request, EstimationExportService $exportService): StreamedResponse
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'season_id' => ['required', 'exists:estimation_seasons,id'],
            'type' => ['required', 'string', 'max:32'],
            'period_start_week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'period_end_week' => ['nullable', 'integer', 'min:1', 'max:53'],
        ]);

        $season = EstimationSeason::findOrFail($data['season_id']);
        $version = EstimationVersion::query()
            ->where('season_id', $data['season_id'])
            ->where('type', $data['type'])
            ->where('period_start_week', $data['period_start_week'] ?? null)
            ->where('period_end_week', $data['period_end_week'] ?? null)
            ->where('status', EstimationVersionStatus::ACTIVE->value)
            ->first();

        return $exportService->streamTemplate($season, $version);
    }
}
