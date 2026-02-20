<?php

namespace App\Http\Controllers\Planning;

use App\Enums\EstimationVersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Models\Especie;
use App\Models\EstimationBiweeklyVersion;
use App\Models\EstimationSeason;
use App\Models\Service;
use App\Services\EstimationBiweeklyImportService;
use Illuminate\Http\Request;
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
}
