<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PreCooling\Concerns\AuthorizesPreCooling;
use App\Models\PreCoolingSaldo;
use App\Services\PreCooling\CamaraSaldoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaldoController extends Controller
{
    use AuthorizesPreCooling;

    public function __construct(
        private readonly CamaraSaldoService $camaraSaldoService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.saldos.manage');

        $data = $request->validate([
            'camara_id' => 'required|integer|exists:pre_cooling_camaras,id',
            'banda' => 'required|string|max:50',
            'fila' => 'required|string|max:50',
            'columna' => 'required|string|max:50',
            'altura' => 'required|string|max:50',
            'nivel' => 'required|string|max:50',
            'folio' => 'required|string|max:50',
            'tipo_proceso_id' => 'nullable|integer|exists:pre_cooling_tipos_procesos,id',
            'cajas' => 'nullable|integer|min:0',
            'pallets' => 'nullable|integer|min:0',
            'especie' => 'nullable|string|max:255',
            'variedad' => 'nullable|string|max:255',
            'productor' => 'nullable|string|max:255',
        ]);

        $this->camaraSaldoService->ingresarManual(
            (int) $data['camara_id'],
            $data['banda'],
            $data['fila'],
            $data['columna'],
            $data['altura'],
            $data['nivel'],
            $data['folio'],
            $data,
            $request->user(),
        );

        return back()->with('success', 'Folio ingresado manualmente en la cámara.');
    }

    public function update(Request $request, PreCoolingSaldo $saldo): RedirectResponse
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.saldos.manage');

        $data = $request->validate([
            'camara_id' => 'required|integer|exists:pre_cooling_camaras,id',
            'banda' => 'required|string|max:50',
            'fila' => 'required|string|max:50',
            'columna' => 'required|string|max:50',
            'altura' => 'required|string|max:50',
            'nivel' => 'required|string|max:50',
        ]);

        $this->camaraSaldoService->ubicar(
            $saldo->id,
            (int) $data['camara_id'],
            $data['banda'],
            $data['fila'],
            $data['columna'],
            $data['altura'],
            $data['nivel'],
            $request->user(),
        );

        return back()->with('success', 'Folio asignado a la posición correctamente.');
    }

    public function destroy(Request $request, PreCoolingSaldo $saldo): RedirectResponse
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.saldos.manage');

        $this->camaraSaldoService->retirar($saldo->id, $request->user());

        return back()->with('success', 'Folio retirado de la cámara correctamente.');
    }
}
