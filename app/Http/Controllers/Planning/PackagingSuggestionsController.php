<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Services\Planning\CarozosPackagingMatrixService;
use App\Services\Planning\InventoryRepositorySqlsrv;
use App\Services\Planning\QualityRepositoryMysql;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PackagingSuggestionsController extends Controller
{
    use AuthorizesPlanning;

    public function __construct(
        private readonly InventoryRepositorySqlsrv $inventoryRepository,
        private readonly QualityRepositoryMysql $qualityRepository,
        private readonly CarozosPackagingMatrixService $matrixService,
    ) {
    }

    public function forReception(Request $request)
    {
        $this->authorizePlanning($request);

        $data = $request->validate([
            'n_g_recepcion' => ['required', 'string', 'max:40'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
            'destino' => ['nullable', 'string', 'max:40'],
            'destinos' => ['nullable', 'array', 'max:10'],
            'destinos.*' => ['string', 'max:40'],
        ]);

        $n = trim((string) $data['n_g_recepcion']);
        if ($n === '') {
            return response()->json(['data' => []]);
        }

        Log::debug('Planning packaging suggestions request', ['n_g_recepcion' => $n]);

        $inv = $this->inventoryRepository->getAvailableLots([
            'n_g_recepcion' => $n,
            'limit' => 1,
        ])->first();

        if (! is_array($inv)) {
            Log::debug('Planning packaging suggestions: inventory not found', ['n_g_recepcion' => $n]);
            return response()->json(['data' => []]);
        }

        $qualityMap = $this->qualityRepository->getQualityByNGRecepcion([$n]);
        $snap = $qualityMap[$n] ?? [];

        $destinos = [];
        if (! empty($data['destino'])) {
            $destinos[] = (string) $data['destino'];
        }
        if (! empty($data['destinos']) && is_array($data['destinos'])) {
            $destinos = array_merge($destinos, $data['destinos']);
        }
        $destinos = collect($destinos)->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all();

        $snap = is_array($snap) ? $snap : [];
        if (! empty($destinos)) {
            $snap['allowed_destinos'] = $destinos;
        }

        $limit = (int) ($data['limit'] ?? 12);
        $out = $this->matrixService->suggestOptions($inv, $snap, $limit);

        Log::debug('Planning packaging suggestions response', ['n_g_recepcion' => $n, 'count' => count($out)]);

        return response()->json([
            'data' => $out,
        ]);
    }
}
