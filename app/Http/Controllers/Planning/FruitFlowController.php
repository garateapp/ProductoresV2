<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Models\Especie;
use App\Models\EstimationSeason;
use App\Services\Planning\FruitFlowMatrixService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FruitFlowController extends Controller
{
    use AuthorizesPlanning;

    public function __construct(private readonly FruitFlowMatrixService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorizePlanning($request);

        $seasons = EstimationSeason::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get(['id', 'code', 'name', 'is_active']);

        $activeSeasonId = (int) ($seasons->firstWhere('is_active', true)?->id ?? $seasons->first()?->id ?? 0);

        $filters = [
            'season_id' => (int) ($request->query('season_id') ?? $activeSeasonId),
            'especie' => $request->query('especie', ''),
            'variedad' => $request->query('variedad', ''),
            // Anchor (día dentro de la "semana actual"). Matriz muestra: semana anterior, actual y siguiente.
            'anchor' => $request->query('anchor', now()->toDateString()),
            'producer_q' => $request->query('producer_q', ''),
            'only_active_producers' => $request->boolean('only_active_producers', true),
        ];

        $matrix = $this->service->build([
            ...$filters,
            'especie' => $filters['especie'] !== '' ? (string) $filters['especie'] : null,
            'variedad' => $filters['variedad'] !== '' ? (string) $filters['variedad'] : null,
            'producer_q' => $filters['producer_q'] !== '' ? (string) $filters['producer_q'] : null,
        ]);

        $especies = Especie::query()->orderBy('name')->pluck('name')->values();

        return Inertia::render('Planning/FruitFlow/Index', [
            'seasons' => $seasons,
            'especies' => $especies,
            'filters' => $filters,
            'matrix' => $matrix,
        ]);
    }
}
