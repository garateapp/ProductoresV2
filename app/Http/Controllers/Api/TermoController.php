<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TermoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'planta_id' => ['required', 'integer'],
            'folio' => ['required', 'string'],
        ]);

        $view = (string) config('services.termo.sqlsrv_view', 'V_PKG_Produccion_Salidas_XXX');

        try {
            $rows = DB::connection('sqlsrv')->select(
                <<<SQL
                SELECT n_exportadora as exportadora,
                       folio as folio,
                       n_productor as productor,
                       n_especie as especie,
                       n_variedad as variedad,
                       n_embalaje as embalaje,
                       n_categoria as categoria,
                       n_calibre as calibre,
                       SUM(cantidad) as cantidad
                  FROM {$view}
                 WHERE folio = ?
                 GROUP BY n_exportadora, folio, n_productor, n_especie, n_variedad, n_embalaje, n_categoria, n_calibre
                SQL,
                [$validated['folio']]
            );
        } catch (\Throwable $e) {
            Log::error('TERMO_ENDPOINT_ERROR', [
                'planta_id' => $validated['planta_id'],
                'folio' => $validated['folio'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error consultando la base de datos',
                'error' => [
                    'code' => 'DB_ERROR',
                    'details' => 'No se pudo completar la consulta',
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'planta_id' => (int) $validated['planta_id'],
            'folio' => $validated['folio'],
            'data' => $rows,
        ]);
    }
}
