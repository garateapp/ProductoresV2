<?php

namespace App\Http\Controllers\Detenciones;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Detenciones\Concerns\AuthorizesDetenciones;
use App\Services\Detenciones\ResumenService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use AuthorizesDetenciones;

    public function __construct(private readonly ResumenService $resumenService)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeDetenciones($request);

        $resumen = $this->resumenService->resumen(
            $request->input('fecha_desde'),
            $request->input('fecha_hasta'),
        );

        return Inertia::render('Detenciones/Dashboard', [
            'resumen' => $resumen,
        ]);
    }
}