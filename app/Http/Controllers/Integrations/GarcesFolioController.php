<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Services\Garces\GarcesProduccionFolioService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GarcesFolioController extends Controller
{
    public function index(Request $request, GarcesProduccionFolioService $service)
    {
        $filters = [
            'q' => (string) $request->query('q', ''),
            'desde' => (string) $request->query('desde', now()->subDays(7)->toDateString()),
            'hasta' => (string) $request->query('hasta', now()->toDateString()),
            'estado' => (string) $request->query('estado', 'pendientes'),
        ];

        $folios = $service->availableFolios($filters, 25)->withQueryString();

        return Inertia::render('Integrations/Garces/Folios/Index', [
            'folios' => $folios,
            'filters' => $filters,
            'config' => [
                'enabled' => $service->isEnabled(),
                'endpoint_configured' => $service->endpoint() !== '',
                'max_folios' => GarcesProduccionFolioService::MAX_FOLIOS,
            ],
            'sourceError' => $service->sourceError,
        ]);
    }

    public function preview(Request $request, GarcesProduccionFolioService $service)
    {
        $validated = $this->validateFolios($request);

        return response()->json($service->previewFolios($validated['folios']));
    }

    public function sync(Request $request, GarcesProduccionFolioService $service)
    {
        $validated = $this->validateFolios($request);

        $result = $service->syncFolios($validated['folios'], $request->user()?->id);
        $status = $result['status'] ?? 'error';
        $ok = in_array($status, ['completed', 'partial'], true);

        return back()
            ->with($ok ? 'success' : 'error', $result['message'] ?? 'Estado: '.$status)
            ->with('garces_sync', $result);
    }

    private function validateFolios(Request $request): array
    {
        return $request->validate([
            'folios' => ['required', 'array', 'min:1', 'max:'.GarcesProduccionFolioService::MAX_FOLIOS],
            'folios.*' => ['string', 'max:100'],
        ], [], [
            'folios' => 'folios',
        ]);
    }
}
