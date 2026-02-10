<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresEstimationsAccess;
use App\Http\Resources\EstimationWeekResource;
use App\Models\EstimationSeason;
use App\Models\EstimationWeek;
use Illuminate\Http\Request;

class EstimationWeekController extends Controller
{
    use EnsuresEstimationsAccess;

    public function store(Request $request, EstimationSeason $estimation_season)
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'week_number' => ['required', 'integer', 'min:1', 'max:53'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ]);

        $week = $estimation_season->weeks()->create($data);

        if ($request->expectsJson()) {
            return new EstimationWeekResource($week);
        }

        return back()->with('success', 'Semana creada.');
    }

    public function update(Request $request, EstimationWeek $estimation_week)
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'week_number' => ['required', 'integer', 'min:1', 'max:53'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ]);

        $estimation_week->update($data);

        if ($request->expectsJson()) {
            return new EstimationWeekResource($estimation_week);
        }

        return back()->with('success', 'Semana actualizada.');
    }

    public function destroy(Request $request, EstimationWeek $estimation_week)
    {
        $this->ensureEstimationsAccess($request);

        $estimation_week->delete();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('success', 'Semana eliminada.');
    }
}
