<?php

namespace App\Services\Inventory;

use App\Models\InventoryMaterial;
use App\Models\InventoryMaterialFamily;
use App\Models\InventoryUnit;
use Illuminate\Support\Facades\DB;

class MaterialCatalogService
{
    private const SAP_GROUP_CODES = [127, 139, 312, 142, 150, 140, 444, 405, 124, 141, 129, 102, 130, 311, 313, 327, 329, 331, 400, 401, 403, 421, 438, 458, 459];

    public function syncFromSap(): array
    {
        $rows = DB::connection('sap')
            ->table('dbo.tOITM')
            ->select([
                'AvgPrice',
                'CodeBars',
                'CreateDate',
                'frozenFor',
                'IndirctTax',
                'InvntItem',
                'InvntryUom',
                'ItemCode',
                'ItemName',
                'ItmsGrpCod',
                'ObjType',
                'OnHand',
                'UpdateDate',
                'validFor',
            ])
            ->whereIn('ItmsGrpCod', self::SAP_GROUP_CODES)
            ->orderBy('ItmsGrpCod')
            ->get();

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated): void {
            foreach ($rows as $row) {
                $code = trim((string) ($row->ItemCode ?? ''));
                $name = trim((string) ($row->ItemName ?? ''));

                if ($code === '' || $name === '') {
                    continue;
                }

                $familyCode = trim((string) ($row->ItmsGrpCod ?? ''));
                $family = $familyCode !== ''
                    ? InventoryMaterialFamily::firstOrCreate(
                        ['codigo' => $familyCode],
                        ['nombre' => 'Grupo SAP '.$familyCode, 'activo' => true]
                    )
                    : null;

                $unitCode = trim((string) ($row->InvntryUom ?? 'UN'));
                $unit = InventoryUnit::firstOrCreate(
                    ['codigo' => $unitCode !== '' ? $unitCode : 'UN'],
                    ['nombre' => $unitCode !== '' ? $unitCode : 'UN']
                );

                $material = InventoryMaterial::where('codigo', $code)->first();

                $payload = [
                    'nombre' => $name,
                    'descripcion' => trim((string) ($row->CodeBars ?? '')) !== ''
                        ? 'Código de barras: '.trim((string) $row->CodeBars)
                        : null,
                    'family_id' => $family?->id,
                    'unit_id' => $unit->id,
                    'tipo_material' => (string) ($row->InvntItem ?? 'Y') === 'Y' ? 'consumo' : 'retornable',
                    'sap_on_hand' => (float) ($row->OnHand ?? 0),
                    'sap_avg_price' => $row->AvgPrice !== null ? (float) $row->AvgPrice : null,
                    'activo' => (string) ($row->validFor ?? 'Y') === 'Y' && (string) ($row->frozenFor ?? 'N') !== 'Y',
                    'metadata' => [
                        'sap' => [
                            'code_bars' => $row->CodeBars ?? null,
                            'create_date' => $row->CreateDate ?? null,
                            'update_date' => $row->UpdateDate ?? null,
                            'indirect_tax' => $row->IndirctTax ?? null,
                            'obj_type' => $row->ObjType ?? null,
                        ],
                    ],
                ];

                if ($material) {
                    $material->fill($payload)->save();
                    $updated++;
                    continue;
                }

                InventoryMaterial::create(array_merge(['codigo' => $code], $payload));
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
