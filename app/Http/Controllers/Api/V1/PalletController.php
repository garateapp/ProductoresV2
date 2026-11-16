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
    private const SALIDAS_CONNECTIONS = ['sqlsrv'];

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

        if (!$salida) {
            return response()->json([
                'message' => 'Folio no registrado en salidas de producción.',
            ], 404);
        }
       $variedades = [];
$productores = [];
$calibres = [];
$categorias = [];
$fechas_embalaje = [];

foreach ($salida as $item) {
    // Formatear fecha a DD-MM-YYYY
    $fecha_formateada = !empty($item->fecha_produccion_sf) 
        ? date('d-m-Y', strtotime($item->fecha_produccion_sf)) 
        : '';

    if (!empty($item->n_variedad)) {
        $variedades[] = $item->n_variedad;
    }
    if (!empty($item->n_productor)) {
        $productores[] = $item->n_productor;
    }
    if (!empty($item->n_calibre)) {
        $calibres[] = $item->n_calibre;
    }
    if (!empty($item->n_categoria)) {
        $categorias[] = $item->n_categoria;
    }
    if (!empty($fecha_formateada)) {
        $fechas_embalaje[] = $fecha_formateada;
    }
}

// Eliminar duplicados y unir con ", " (sin dejar una coma sobrante al final)
$variedad       = implode(', ', array_unique($variedades));
$productor      = implode(', ', array_unique($productores));
$calibre        = implode(', ', array_unique($calibres));
$categoria      = implode(', ', array_unique($categorias));
$fecha_embalaje = implode(', ', array_unique($fechas_embalaje));
        $cEmbalaje = trim((string) $salida[0]->c_embalaje);

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

        return response()->json([
            'folio' => $folio,
            'c_embalaje' => $cEmbalaje,
            'technical_sheet' => new PalletTechnicalSheetResource($sheet, $folio, $cEmbalaje),
            'variedad'=>$variedad,
            'productor'=>$productor,
            'calibre'=>$calibre,
            'categoria'=>$categoria,
            'fecha_embalaje'=>$fecha_embalaje,
            'sheet'=>$sheet,
        ]);
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
                    ->select(['folio', 'c_embalaje','n_variedad','n_productor','n_calibre','n_categoria','fecha_produccion_sf'])
                    ->get();
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
