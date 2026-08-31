<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryAutoConsumptionFolio;
use App\Services\Inventory\AutoConsumptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutoConsumptionFolioController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $query = InventoryAutoConsumptionFolio::query()
            ->with(['packaging', 'originLocation', 'movement']);

        if ($estado = $request->string('estado')->trim()->toString()) {
            $query->where('estado', $estado);
        }

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('folio', 'like', '%'.$search.'%')
                ->orWhere('n_embalaje', 'like', '%'.$search.'%')
                ->orWhere('c_embalaje', 'like', '%'.$search.'%'));
        }

        $folios = $query
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (InventoryAutoConsumptionFolio $folio) => [
                'id' => $folio->id,
                'folio' => $folio->folio,
                'es_temporal' => (bool) $folio->es_temporal,
                'c_embalaje' => $folio->c_embalaje,
                'n_embalaje' => $folio->n_embalaje,
                'cantidad' => (float) $folio->cantidad,
                'n_linea_proceso' => $folio->n_linea_proceso,
                'n_turno' => $folio->n_turno,
                'fecha_produccion' => $folio->fecha_produccion?->toDateString(),
                'estado' => $folio->estado,
                'detalle_estado' => $folio->detalle_estado,
                'origin_location_id' => $folio->origin_location_id,
                'origin_location' => $folio->originLocation ? $folio->originLocation->nombre : null,
                'movement_id' => $folio->movement_id,
                'movement_estado' => $folio->movement?->estado,
                'processed_at' => $folio->processed_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Inventory/AutoConsumptionFolios/Index', [
            'folios' => $folios,
            'filters' => [
                'estado' => (string) $request->string('estado'),
                'q' => (string) $request->string('q'),
            ],
        ]);
    }

    public function retry(Request $request, InventoryAutoConsumptionFolio $folio, AutoConsumptionService $service): RedirectResponse
    {
        $this->authorizeInventory($request);

        $result = $service->retry($folio);

        if (in_array($result['estado'], ['aplicado', 'temporal'], true)) {
            $mensaje = $result['estado'] === 'temporal'
                ? 'quedó en consumo temporal.'
                : 'aplicado correctamente.';

            return back()->with('success', "Folio {$folio->folio} {$mensaje}");
        }

        $detalle = $result['detalle_estado'] ?: 'El folio no pudo ser aplicado.';
        $estado = match ($result['estado']) {
            'sin_embalaje' => 'sin embalaje',
            'sin_ficha' => 'sin ficha técnica',
            default => 'borrador',
        };

        return back()->with('error', "Folio {$folio->folio} quedó en {$estado}: {$detalle}");
    }
}