<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FolioDeduction;
use App\Models\PackingProcessLot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FolioDeductionController extends Controller
{
    public function deduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'processNumber' => ['required', 'string'],
            'barcode' => ['required', 'string'],
        ]);

        $processId = $validated['processNumber'];
        $folio = $validated['barcode'];


        //$packingProcessLot = PackingProcessLot::where('process_id', $processId)->first();

        $lote_recepcion=substr($folio, 0, 4); // Asumiendo que el número de lote está en los primeros 10 caracteres del código de barras

        // if (! $packingProcessLot) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Proceso no encontrado',
        //         'error' => [
        //             'code' => 'PROCESS_NOT_FOUND',
        //             'details' => "No existe el proceso {$processId}",
        //         ],
        //     ], 404);
        // }
        Log::info("lote_recepcion: ".$lote_recepcion);
        $foliosLote=DB::connection('sqlsrv')
        ->table('V_PKG_Recepcion_FG')
        ->select('folio')
        ->where('numero_g_Recepcion',$lote_recepcion)
        ->where('id_empresa','1')
        ->where('destruccion_tipo','')
        ->where('destruccion_id',0)
        ->get();

        if(! $foliosLote->contains('folio', $folio)){
            return response()->json([
                'success' => false,
                'message' => 'Folio no válido para este proceso',
                'error' => [
                    'code' => 'INVALID_FOLIO',
                    'details' => "El folio {$folio} no pertenece al proceso {$processId}",
                ],
            ], 400);
        }
        // Validar que el proceso exista en SQL Server
        $produccion = DB::connection('sqlsrv')
            ->table('PKG_G_Produccion')
            ->where('numero_i', $processId)
            ->select('id', 'numero_i')
            ->first();

        if (! $produccion) {
            return response()->json([
                'success' => false,
                'message' => 'Proceso no encontrado',
                'error' => [
                    'code' => 'PROCESS_NOT_FOUND',
                    'details' => "No existe el proceso con numero_i {$processId}",
                ],
            ], 404);
        }

        // Validar que el folio exista en SQL Server
        $stockDet = DB::connection('sqlsrv')
            ->table('PKG_Stock_Det')
            ->where('folio', $folio)
            ->select('id', 'folio')
            ->first();

        if (! $stockDet) {
            return response()->json([
                'success' => false,
                'message' => 'Folio no encontrado',
                'error' => [
                    'code' => 'FOLIO_NOT_FOUND',
                    'details' => "No existe el folio {$folio} en stock",
                ],
            ], 404);
        }

        // Verificar que no se haya escaneado antes (duplicado)
        $existing = FolioDeduction::where('process_id', $produccion->id)
            ->where('folio', $folio)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Folio ya fue escaneado para este proceso',
                'error' => [
                    'code' => 'DUPLICATE_SCAN',
                    'details' => "El folio {$folio} ya fue rebajado en el proceso {$processId} el {$existing->scanned_at->format('d/m/Y H:i')}",
                ],
            ], 409);
        }

        // Registrar la rebaja
        $deduction = FolioDeduction::create([
            'process_id' => $produccion->id,
            'folio' => $folio,
            'user_id' => $request->user()?->id,
            'quantity' => 1,
            'scanned_at' => now(),
        ]);
        $now = Carbon::now()->format('H:i:s'); // hora actual
        $turno=DB::connection('sqlsrv')
        ->table('PRO_P_Turnos')
         ->where(function ($q) use ($now) {
        // NO cruza medianoche: inicio < termino  AND now in [inicio, termino)
        $q->whereRaw('CAST(hora_inicio AS time) < CAST(hora_termino AS time)')
          ->whereRaw('CAST(? AS time) >= CAST(hora_inicio AS time)', [$now])
          ->whereRaw('CAST(? AS time) <  CAST(hora_termino AS time)', [$now]);
    })
    ->orWhere(function ($q) use ($now) {
        // SÍ cruza medianoche: inicio > termino AND (now >= inicio OR now < termino)
        $q->whereRaw('CAST(hora_inicio AS time) > CAST(hora_termino AS time)')
          ->where(function ($q2) use ($now) {
              $q2->whereRaw('CAST(? AS time) >= CAST(hora_inicio AS time)', [$now])
                 ->orWhereRaw('CAST(? AS time) <  CAST(hora_termino AS time)', [$now]);
          });
    })
    ->first();
    $turno_id=$turno->id;
    Log::debug("Updating stock det with id {$stockDet->id} to set destruccion_tipo=PRN and destruccion_id={$produccion->id}");
         DB::connection('sqlsrv')
         ->table('PKG_Stock_Det')
         ->where('id', $stockDet->id)
         ->update(['destruccion_tipo' => 'PRN', 'destruccion_id' => $produccion->id,'turno_id'=>$turno_id]);
        $totalDeductions = FolioDeduction::where('process_id', $produccion->id)->count();
        Log::debug("Updating stock det with id {$stockDet->id} to set destruccion_tipo=PRN and destruccion_id={$produccion->id} and turno_id={$turno_id}");
             DB::connection('sqlsrv')
             ->table('PKG_Stock_Det')
             ->where('id', $stockDet->id)
             ->update(['destruccion_tipo' => 'PRN', 'destruccion_id' => $produccion->id,'id_pro_p_turno_destruccion'=>$turno_id]);

        return response()->json([
            'success' => true,
            'message' => 'Folio rebajado exitosamente',
            'data' => [
                'processNumber' => $processId,
                'barcode' => $folio,
                'folioName' => $folio,
                'previousQuantity' => $totalDeductions,
                'deductedQuantity' => 1,
                'remainingQuantity' => $totalDeductions,
                'timestamp' => $deduction->scanned_at->toIso8601String(),
            ],
        ]);
    }
}
