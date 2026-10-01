<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Services\Inventory\PendingTransformationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendingTransformationController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request, PendingTransformationService $service): JsonResponse
    {
        $this->authorizeInventory($request);

        return response()->json($service->overview());
    }

    public function store(Request $request, PendingTransformationService $service): JsonResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:64'],
            'state' => ['nullable', 'string', 'in:pending,failed'],
        ]);

        $id = $data['id'] ?? null;
        $state = $data['state'] ?? 'pending';

        if ($id !== null && $state === 'pending' && ! ctype_digit($id)) {
            return response()->json([
                'ok' => false,
                'message' => 'El identificador del trabajo pendiente debe ser numérico.',
                'overview' => $service->overview(),
            ], 422);
        }

        if ($id === null) {
            $summary = $service->processAll();

            return response()->json([
                'ok' => $summary['failed'] === 0,
                'message' => $summary['failed'] === 0
                    ? "Se procesaron {$summary['processed']} consumo(s) pendiente(s)."
                    : "{$summary['processed']} proceso(s) ok, {$summary['failed']} con error.",
                'summary' => $summary,
                'overview' => $service->overview(),
            ]);
        }

        $result = $state === 'failed'
            ? $service->retryFailed($id)
            : $service->processPending((int) $id);

        return response()->json([
            ...$result,
            'overview' => $service->overview(),
        ], $result['ok'] ? 200 : 422);
    }
}