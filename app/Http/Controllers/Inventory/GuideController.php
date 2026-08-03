<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        return Inertia::render('Inventory/Guide', [
            'roles' => [
                'Operador de Bodega',
                'Supervisor de Bodega',
                'Auditor / Control de Gestión',
            ],
        ]);
    }
}
