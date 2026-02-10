<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Services\Planning\PackagingRepositorySqlsrv;
use Illuminate\Http\Request;

class PackagingController extends Controller
{
    use AuthorizesPlanning;

    public function __construct(private readonly PackagingRepositorySqlsrv $packagingRepository)
    {
    }

    public function search(Request $request)
    {
        $this->authorizePlanning($request);

        $q = (string) $request->query('q', '');
        $items = $this->packagingRepository->searchPackagings($q, 25);

        return response()->json([
            'data' => $items,
        ]);
    }
}

