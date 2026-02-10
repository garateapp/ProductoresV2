<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresEstimationsAccess;
use App\Models\EstimationSeason;
use App\Models\EstimationStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EstimationMaintainerController extends Controller
{
    use EnsuresEstimationsAccess;

    public function index(Request $request)
    {
        $this->ensureEstimationsAccess($request);

        $seasons = EstimationSeason::with(['weeks' => fn ($q) => $q->orderBy('week_number')])
            ->orderByDesc('id')
            ->get();

        $statuses = EstimationStatus::orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Estimations/Maintainers', [
            'seasons' => $seasons,
            'statuses' => $statuses,
        ]);
    }
}
