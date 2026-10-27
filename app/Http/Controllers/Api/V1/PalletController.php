<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PalletTechnicalSheetResource;
use App\Models\InventoryPackaging;
use App\Models\InventoryTechnicalSheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PalletController extends Controller
{
    private const SALIDAS_VIEW = 'V_PKG_Stock_Inventario';

    /**
     * Temporada actual y anterior: un folio puede seguir vigente aunque
     * su temporada ya haya sido cerrada.
     */
    private const SALIDAS_CONNECTIONS = ['sqlsrv', 'temporada_anterior'];

    public function show(string $folio): JsonResponse|PalletTechnicalSheetResource
    {
        $folio = trim($folio);

        try {
            $salida = $this->findSalida($folio);
        } catch (\PDOException $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo conectar con el sistema de producción.',
            ], 503);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Error al consultar las salidas de producción.',
            ], 500);
        }

        if (! $salida) {
            return response()->json([
                'message' => 'Folio no registrado en salidas de producción.',
            ], 404);
        }

        $cEmbalaje = trim((string) $salida->c_embalaje);

        $packaging = InventoryPackaging::query()
            ->where('codigo', $cEmbalaje)
            ->first();

        if (! $packaging) {
            return response()->json([
                'message' => "El embalaje {$cEmbalaje} no se encuentra homologado.",
            ], 404);
        }

        $sheet = InventoryTechnicalSheet::query()
            ->where('packaging_id', $packaging->id)
            ->where('activo', true)
            ->whereDate('fecha_vigencia_desde', '<=', now())
            ->where(function ($query) {
                $query->whereNull('fecha_vigencia_hasta')
                    ->orWhereDate('fecha_vigencia_hasta', '>=', now());
            })
            ->with([
                'packaging:id,codigo,nombre',
                'material:id,codigo,nombre',
                'unitItems.material:id,codigo,nombre',
                'unitItems.replacementMaterial:id,codigo,nombre',
                'palletItems.material:id,codigo,nombre',
                'palletItems.replacementMaterial:id,codigo,nombre',
            ])
            ->orderByDesc('version')
            ->first();

        if (! $sheet) {
            return response()->json([
                'message' => "No existe ficha técnica vigente para el embalaje {$cEmbalaje}.",
            ], 404);
        }
        Log::info('PalletController@show', [
            'folio' => $folio,
            'c_embalaje' => $cEmbalaje,
            'technical_sheet_id' => $sheet->id,
            'sheet' => $sheet->toArray(),
        ]);

        return new PalletTechnicalSheetResource($sheet, $folio, $cEmbalaje);
    }

    private function findSalida(string $folio): ?object
    {
        $successfulQueries = 0;
        $lastException = null;

        foreach (self::SALIDAS_CONNECTIONS as $connection) {
            try {
                $salida = DB::connection($connection)
                    ->table(self::SALIDAS_VIEW)
                    ->where('folio', $folio)
                    ->select(['folio', 'c_embalaje'])
                    ->first();
                $successfulQueries++;

                if ($salida) {
                    return $salida;
                }
            } catch (\PDOException $exception) {
                $lastException = $exception;
                report($exception);
            }
        }

        if ($successfulQueries === 0 && $lastException) {
            throw $lastException;
        }

        return null;
    }
}
