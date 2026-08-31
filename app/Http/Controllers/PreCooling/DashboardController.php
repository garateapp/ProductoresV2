<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PreCooling\Concerns\AuthorizesPreCooling;
use App\Services\PreCooling\ReporteService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use AuthorizesPreCooling;

    public function __construct(
        private readonly ReporteService $reporteService,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorizePreCooling($request);

        return Inertia::render('PreCooling/Dashboard', [
            'resumen' => $this->reporteService->resumen(),
        ]);
    }
}
