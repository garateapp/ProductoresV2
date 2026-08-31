<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Services\PreCooling\IntegracionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegracionController extends Controller
{
    public function __construct(
        private readonly IntegracionService $integracionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'estado' => 'nullable|string|in:ingresado,iniciado,salido',
        ]);

        $payload = $this->integracionService->generar(
            $data['fecha_inicio'] ?? null,
            $data['fecha_fin'] ?? null,
            $data['estado'] ?? null,
        );

        return response()->json($payload);
    }
}
