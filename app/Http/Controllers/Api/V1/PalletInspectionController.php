<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePalletInspectionRequest;
use App\Models\PalletInspection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PalletInspectionController extends Controller
{
    public function store(StorePalletInspectionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $inspection = DB::transaction(function () use ($validated, $request): PalletInspection {
            $todosCumplen = collect($validated['items'])
                ->every(fn (array $item) => (bool) $item['cumple']);

            $inspection = PalletInspection::query()->create([
                'folio' => $validated['folio'],
                'c_embalaje' => $validated['c_embalaje'],
                'technical_sheet_id' => $validated['technical_sheet_id'],
                'user_id' => $request->user()->id,
                'resultado' => $todosCumplen ? 'aprobado' : 'rechazado',
                'observaciones_generales' => $validated['observaciones_generales'] ?? null,
            ]);

            $inspection->items()->createMany(
                collect($validated['items'])->map(fn (array $item) => [
                    'technical_sheet_pallet_item_id' => $item['technical_sheet_pallet_item_id'],
                    'material_utilizado_id' => $item['material_utilizado_id'],
                    'es_reemplazo' => (bool) $item['es_reemplazo'],
                    'cumple' => (bool) $item['cumple'],
                    'motivo_no_cumple' => $item['motivo_no_cumple'] ?? null,
                ])->all(),
            );

            return $inspection;
        });

        return response()->json([
            'message' => 'Inspección registrada con éxito',
            'resultado' => $inspection->resultado,
            'id' => $inspection->id,
        ], 201);
    }
}
