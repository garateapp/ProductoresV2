<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Http\Requests\Inventory\ResolveInventoryScanRequest;
use App\Services\Inventory\ScanResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScanController extends Controller
{
    use AuthorizesInventory;

    public function resolve(ResolveInventoryScanRequest $request, ScanResolutionService $scanResolutionService): JsonResponse
    {
        $this->authorizeInventory($request);

        return response()->json(
            $scanResolutionService->resolve($request->string('raw_code')->toString(), (int) $request->user()->id, $request->validated())
        );
    }

    public function startSession(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);

        return response()->json([
            'scan_session_uuid' => (string) Str::uuid(),
        ]);
    }

    public function closeSession(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);

        return response()->json([
            'closed' => true,
            'scan_session_uuid' => $request->input('scan_session_uuid'),
        ]);
    }
}
