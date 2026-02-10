<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresEstimationsAccess;
use App\Http\Resources\EstimationSeasonResource;
use App\Enums\EstimationVersionStatus;
use App\Models\EstimationVersion;
use App\Models\EstimationSeason;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EstimationSeasonController extends Controller
{
    use EnsuresEstimationsAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureEstimationsAccess($request);

        $seasons = EstimationSeason::with('weeks')
            ->orderByDesc('id')
            ->get();

        return EstimationSeasonResource::collection($seasons);
    }

    public function store(Request $request)
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:estimation_seasons,code'],
            'name' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ]);

        $season = EstimationSeason::create($data);
        $this->generateWeeksForSeason($season);

        if ($request->expectsJson()) {
            return new EstimationSeasonResource($season);
        }

        return back()->with('success', 'Temporada creada.');
    }

    public function update(Request $request, EstimationSeason $estimation_season)
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:estimation_seasons,code,'.$estimation_season->id],
            'name' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ]);

        $estimation_season->update($data);

        if ($request->expectsJson()) {
            return new EstimationSeasonResource($estimation_season->fresh('weeks'));
        }

        return back()->with('success', 'Temporada actualizada.');
    }

    public function destroy(Request $request, EstimationSeason $estimation_season)
    {
        $this->ensureEstimationsAccess($request);

        $hasActiveVersions = EstimationVersion::where('season_id', $estimation_season->id)
            ->where('status', EstimationVersionStatus::ACTIVE->value)
            ->exists();
        if ($hasActiveVersions) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No se puede eliminar la temporada porque tiene versiones activas.',
                ], 422);
            }

            return back()->withErrors([
                'season' => 'No se puede eliminar la temporada porque tiene versiones activas.',
            ]);
        }

        $estimation_season->delete();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('success', 'Temporada eliminada.');
    }

    private function generateWeeksForSeason(EstimationSeason $season): void
    {
        if (! $season->start_date || ! $season->end_date) {
            return;
        }

        if ($season->weeks()->exists()) {
            return;
        }

        $start = Carbon::parse($season->start_date)->startOfWeek(Carbon::MONDAY);
        $end = Carbon::parse($season->end_date)->endOfWeek(Carbon::SUNDAY);
        $period = new CarbonPeriod($start, '1 week', $end);

        $weeks = [];
        foreach ($period as $date) {
            $weekNumber = (int) $date->isoWeek;
            if (isset($weeks[$weekNumber])) {
                continue;
            }

            $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
            $weekEnd = $date->copy()->endOfWeek(Carbon::SUNDAY);

            $weeks[$weekNumber] = [
                'week_number' => $weekNumber,
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'is_active' => true,
            ];
        }

        if (! empty($weeks)) {
            $season->weeks()->createMany(array_values($weeks));
        }
    }
}
