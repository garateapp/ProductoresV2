<?php

namespace App\Services\Inventory;

use App\Models\InventoryPackaging;
use Illuminate\Support\Facades\DB;

class PackagingCatalogService
{
    public function syncFromSqlsrv(): array
    {
        $rows = collect(DB::connection('sqlsrv')->select("
            SELECT
                  [codigo]
                , [nombre]
                , [tipo]
                , [peso_std]
                , [tramo_sag_embalajes]
                , [descripcion]
                , [CP3] as altura
                , [CP4] as cantidad_cajas
                , [CP5] as multiplicador
            FROM [FX6_Packing_Garate_Operaciones].[dbo].[ADM_P_Items]
            WHERE tipo IN ('IN-EM', 'IN-EN') and activo=1
            ORDER BY nombre
        "));

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated): void {
            foreach ($rows as $row) {
                $code = trim((string) ($row->codigo ?? ''));
                $name = trim((string) ($row->nombre ?? ''));

                if ($code === '' || $name === '') {
                    continue;
                }

                $packaging = InventoryPackaging::where('codigo', $code)->first();

                $payload = [
                    'nombre' => $name,
                    'tipo' => $row->tipo ? trim((string) $row->tipo) : null,
                    'peso_std' => $row->peso_std !== null ? (float) $row->peso_std : null,
                    'tramo_sag_embalajes' => $row->tramo_sag_embalajes ? trim((string) $row->tramo_sag_embalajes) : null,
                    'descripcion' => $row->descripcion ? trim((string) $row->descripcion) : null,
                    'altura' => $row->altura ? trim((string) $row->altura) : null,
                    'cantidad_cajas' => $row->cantidad_cajas !== null ? (float) $row->cantidad_cajas : null,
                    'multiplicador' => $row->multiplicador !== null ? (float) $row->multiplicador : null,
                    'activo' => true,
                    'metadata' => [
                        'origen' => 'sqlsrv',
                    ],
                ];

                if ($packaging) {
                    $packaging->fill($payload)->save();
                    $updated++;
                    continue;
                }

                InventoryPackaging::create(array_merge(['codigo' => $code], $payload));
                $created++;
            }
        });

        return [
            'total' => $rows->count(),
            'created' => $created,
            'updated' => $updated,
        ];
    }
}
