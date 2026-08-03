<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Services\Inventory\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    use AuthorizesInventory;

    public function verify(Request $request, LedgerService $ledgerService): JsonResponse
    {
        $this->authorizeInventory($request);

        return response()->json(
            $ledgerService->verifyChain($request->integer('from_sequence') ?: null)
        );
    }
}
