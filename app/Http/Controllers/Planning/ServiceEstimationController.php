<?php

namespace App\Http\Controllers\Planning;

use App\Enums\EstimationVersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Models\Especie;
use App\Models\EstimationBiweeklyRow;
use App\Models\EstimationBiweeklyVersion;
use App\Models\EstimationSeason;
use App\Models\Service;
use App\Services\EstimationBiweeklyImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;

class ServiceEstimationController extends Controller
{
    use AuthorizesPlanning;

    public function index(Request $request)
    {
        $this->authorizePlanning($request);

        $versions = EstimationBiweeklyVersion::query()
            ->where('origin', 'servicio_planificador')
            ->with(['season:id,code,name', 'uploader:id,name'])
            ->withCount('rows')
            ->when($request->filled('season_id'), fn ($query) => $query->where('season_id', (int) $request->input('season_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $seasons = EstimationSeason::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get(['id', 'code', 'name', 'is_active']);

        $services = Service::query()
            ->whereNotIn('id', [4, 6])
            ->whereNotNull('owner_id')
            ->with(['owner:id,name,csg'])
            ->orderBy('name')
            ->get(['id', 'name', 'owner_id'])
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'owner_id' => $service->owner_id,
                'owner_name' => $service->owner?->name,
                'owner_csg' => $service->owner?->csg,
            ])
            ->values();

        $especies = Especie::query()
            ->with(['variedads' => fn ($query) => $query->orderBy('name')->select(['id', 'name', 'especie_id'])])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Especie $especie) => [
                'id' => $especie->id,
                'name' => $especie->name,
                'variedades' => $especie->variedads
                    ->map(fn ($variedad) => [
                        'id' => $variedad->id,
                        'name' => $variedad->name,
                    ])
                    ->values(),
            ])
            ->values();

        return Inertia::render('Planning/ServiceEstimations/Index', [
            'versions' => $versions,
            'seasons' => $seasons,
            'services' => $services,
            'especies' => $especies,
            'statuses' => collect(EstimationVersionStatus::cases())->map(fn (EstimationVersionStatus $status) => $status->value)->values()->all(),
            'filters' => $request->only(['season_id', 'status']),
        ]);
    }

    public function store(Request $request, EstimationBiweeklyImportService $importService)
    {
        $this->authorizePlanning($request);

        $version = $importService->createManualServiceVersion($request->all(), $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'version_id' => $version->id,
            ]);
        }

        return redirect()
            ->route('planning.service-estimations.index')
            ->with('success', 'Estimación de servicios guardada como versión #'.$version->id.'.');
    }

    public function show(Request $request, EstimationBiweeklyVersion $estimation_biweekly_version)
    {
        $this->authorizePlanning($request);
        $this->ensureServicePlannerVersion($estimation_biweekly_version);

        $estimation_biweekly_version->load(['season:id,code,name', 'uploader:id,name']);

        $rows = EstimationBiweeklyRow::query()
            ->where('estimation_biweekly_version_id', $estimation_biweekly_version->id)
            ->with(['producer:id,name,csg', 'service:id,name,owner_id', 'service.owner:id,name,csg', 'variedad:id,name'])
            ->orderBy('dia')
            ->orderBy('service_id')
            ->orderBy('id')
            ->get()
            ->map(fn (EstimationBiweeklyRow $row) => [
                'id' => $row->id,
                'service_id' => $row->service_id,
                'service_name' => $row->service?->name,
                'producer_id' => $row->producer_id,
                'producer_name' => $row->producer?->name,
                'csg' => $row->csg,
                'variedad_id' => $row->variedad_id,
                'variedad' => $row->variedad?->name,
                'planta' => $row->planta,
                'tipo' => $row->tipo,
                'acopio' => (bool) $row->acopio,
                'mexico' => $row->mexico,
                'dia' => optional($row->dia)->toDateString(),
                'semana' => $row->semana,
                'total_kilo' => $row->total_kilo,
            ])
            ->values();

        $services = Service::query()
            ->whereNotIn('id', [4, 6])
            ->whereNotNull('owner_id')
            ->with(['owner:id,name,csg'])
            ->orderBy('name')
            ->get(['id', 'name', 'owner_id'])
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'owner_id' => $service->owner_id,
                'owner_name' => $service->owner?->name,
                'owner_csg' => $service->owner?->csg,
            ])
            ->values();

        $variedades = Especie::query()
            ->with(['variedads' => fn ($query) => $query->orderBy('name')->select(['id', 'name', 'especie_id'])])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->flatMap(fn (Especie $especie) => $especie->variedads
                ->map(fn ($variedad) => [
                    'id' => $variedad->id,
                    'name' => $variedad->name,
                    'especie' => $especie->name,
                ]))
            ->sortBy([
                ['especie', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        return Inertia::render('Planning/ServiceEstimations/Show', [
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
            'services' => $services,
            'variedades' => $variedades,
        ]);
    }

    public function updateRow(
        Request $request,
        EstimationBiweeklyVersion $estimation_biweekly_version,
        EstimationBiweeklyRow $estimation_biweekly_row,
        EstimationBiweeklyImportService $importService
    ): Response|RedirectResponse {
        $this->authorizePlanning($request);
        $this->ensureServicePlannerVersion($estimation_biweekly_version);

        if ($estimation_biweekly_row->estimation_biweekly_version_id !== $estimation_biweekly_version->id) {
            abort(404);
        }

        $data = $request->validate([
            'row_id' => ['required', 'integer'],
            'row' => ['required', 'array'],
            'row.service_id' => ['required', 'exists:services,id'],
            'row.variedad_id' => ['required', 'exists:variedads,id'],
            'row.planta' => ['nullable', 'string', 'max:120'],
            'row.tipo' => ['nullable', 'string', 'max:80'],
            'row.acopio' => ['required', 'boolean'],
            'row.mexico' => ['nullable', 'boolean'],
            'row.dia' => ['required', 'date'],
            'row.total_kilo' => ['required', 'numeric', 'min:0.001'],
        ]);

        if ((int) $data['row_id'] !== $estimation_biweekly_row->id) {
            abort(422, 'Row id mismatch.');
        }

        $version = $importService->applyManualServiceUpdate($estimation_biweekly_version, $data, $request->user());

        if ($request->expectsJson()) {
            return response([
                'status' => 'ok',
                'version_id' => $version->id,
            ]);
        }

        return redirect()
            ->route('planning.service-estimations.show', $version)
            ->with('success', 'Fila actualizada. Se generó versión #'.$version->id.'.');
    }

    public function destroyRow(
        Request $request,
        EstimationBiweeklyVersion $estimation_biweekly_version,
        EstimationBiweeklyRow $estimation_biweekly_row,
        EstimationBiweeklyImportService $importService
    ): RedirectResponse {
        $this->authorizePlanning($request);
        $this->ensureServicePlannerVersion($estimation_biweekly_version);

        if ($estimation_biweekly_row->estimation_biweekly_version_id !== $estimation_biweekly_version->id) {
            abort(404);
        }

        $version = $importService->deleteManualServiceRow($estimation_biweekly_version, $estimation_biweekly_row, $request->user());

        return redirect()
            ->route('planning.service-estimations.show', $version)
            ->with('success', 'Fila eliminada. Se generó versión #'.$version->id.'.');
    }

    public function destroy(Request $request, EstimationBiweeklyVersion $estimation_biweekly_version, EstimationBiweeklyImportService $importService): RedirectResponse
    {
        $this->authorizePlanning($request);
        $this->ensureServicePlannerVersion($estimation_biweekly_version);

        $versionId = $estimation_biweekly_version->id;
        $importService->deleteManualServiceVersion($estimation_biweekly_version);

        return redirect()
            ->route('planning.service-estimations.index')
            ->with('success', 'Versión #'.$versionId.' eliminada.');
    }

    private function ensureServicePlannerVersion(EstimationBiweeklyVersion $version): void
    {
        abort_if((string) ($version->origin ?? '') !== 'servicio_planificador', 404);
    }
}
