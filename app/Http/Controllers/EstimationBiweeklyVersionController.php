<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresEstimationsAccess;
use App\Jobs\ImportBiweeklyEstimationsJob;
use App\Models\EstimationBiweeklyRow;
use App\Models\EstimationBiweeklyVersion;
use App\Models\EstimationSeason;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EstimationBiweeklyVersionController extends Controller
{
    use EnsuresEstimationsAccess;

    public function index(Request $request): Responsable
    {
        $this->ensureEstimationsAccess($request);

        $versions = EstimationBiweeklyVersion::query()
            ->with(['season', 'uploader'])
            ->withCount('rows')
            ->when($request->filled('season_id'), fn ($q) => $q->where('season_id', $request->season_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(15);

        $seasons = EstimationSeason::orderByDesc('id')->get(['id', 'code', 'name']);

        return Inertia::render('Estimations/Biweekly/Index', [
            'versions' => $versions,
            'seasons' => $seasons,
            'filters' => $request->only(['season_id', 'status']),
        ]);
    }

    public function show(Request $request, EstimationBiweeklyVersion $estimation_biweekly_version): Responsable
    {
        $this->ensureEstimationsAccess($request);

        $estimation_biweekly_version->load(['season', 'uploader']);

        $rows = EstimationBiweeklyRow::query()
            ->where('estimation_biweekly_version_id', $estimation_biweekly_version->id)
            ->with(['producer', 'agronomist', 'variedad'])
            ->orderBy('dia')
            ->orderBy('producer_id')
            ->paginate(25)
            ->through(function ($row) {
                return [
                    'id' => $row->id,
                    'producer_id' => $row->producer_id,
                    'producer' => $row->producer?->name,
                    'agronomist_id' => $row->agronomist_id,
                    'agronomist' => $row->agronomist?->name,
                    'variedad_id' => $row->variedad_id,
                    'variedad' => $row->variedad?->name,
                    'planta' => $row->planta,
                    'sucursal' => $row->sucursal,
                    'csg' => $row->csg,
                    'especie' => $row->especie,
                    'tipo' => $row->tipo,
                    'acopio' => (bool) $row->acopio,
                    'mexico' => $row->mexico,
                    'dia' => optional($row->dia)->toDateString(),
                    'semana' => $row->semana,
                    'total_kilo' => $row->total_kilo,
                ];
            });

        return Inertia::render('Estimations/Biweekly/Show', [
            'version' => [
                'id' => $estimation_biweekly_version->id,
                'season' => [
                    'id' => $estimation_biweekly_version->season?->id,
                    'code' => $estimation_biweekly_version->season?->code,
                    'name' => $estimation_biweekly_version->season?->name,
                ],
                'period_start_week' => $estimation_biweekly_version->period_start_week,
                'period_end_week' => $estimation_biweekly_version->period_end_week,
                'status' => $estimation_biweekly_version->status?->value ?? $estimation_biweekly_version->status,
                'source' => $estimation_biweekly_version->source,
                'uploader' => $estimation_biweekly_version->uploader?->name,
                'created_at' => optional($estimation_biweekly_version->created_at)->toDateTimeString(),
            ],
            'rows' => $rows,
        ]);
    }

    public function upload(Request $request): Response
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
            'season_id' => ['required', 'exists:estimation_seasons,id'],
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

        $extension = $file->getClientOriginalExtension() ?: 'xlsx';
        $storedPath = Storage::putFileAs('estimations/biweekly', $file, (string) Str::uuid().'.'.$extension);
        $absolutePath = Storage::path($storedPath);

        ImportBiweeklyEstimationsJob::dispatch(
            $absolutePath,
            $storedPath,
            $file->getClientOriginalName(),
            $data,
            $request->user()->id
        );

        if ($request->expectsJson()) {
            return response()->json(['status' => 'queued'], 202);
        }

        return back()->with('success', 'Importacion bisemanal en cola.');
    }
}
