<?php

namespace App\Services\Inventory;

use App\Models\InventoryMaterial;
use App\Models\InventoryMaterialFamily;
use App\Models\InventoryUnit;
use App\Services\Sap\ServiceLayerClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaterialCatalogService
{
    private const SQL_QUERY_NAME = 'vGEX_STOCK';

    private const CENTRAL_WAREHOUSES = ['BD009', 'BD026'];

    private const PRODUCTION_WAREHOUSE = 'BD010';

    public function __construct(
        private readonly ServiceLayerClient $serviceLayerClient,
    ) {}

    public function syncFromSap(): array
    {
        if (config('services.sap_service_layer.enabled')) {
            return $this->syncFromServiceLayer();
        }

        return $this->syncFromSql();
    }

    private function syncFromServiceLayer(): array
    {
        Log::info('Sincronizando catálogo de materiales desde SAP Service Layer', [
            'query' => self::SQL_QUERY_NAME,
        ]);

        $rows = $this->normalizeStockRows(
            $this->serviceLayerClient->sqlQuery(self::SQL_QUERY_NAME)
        );

        return $this->syncRows($rows);
    }

    private function syncFromSql(): array
    {
        $rows = DB::connection('sap')
            ->table('dbo.OITM as T0')
            ->join('dbo.OITW as T1', 'T0.ItemCode', '=', 'T1.ItemCode')
            ->where('T0.InvntItem', 'Y')
            ->whereIn('T1.WhsCode', [...self::CENTRAL_WAREHOUSES, self::PRODUCTION_WAREHOUSE])
            ->select([
                'T0.ItemCode',
                'T0.ItemName',
                'T0.ItmsGrpCod',
                'T0.InvntItem',
                'T1.WhsCode as Bodega',
                'T1.OnHand as Stock',
                'T0.CreateDate',
                'T0.UpdateDate',
                'T0.AvgPrice',
                'T0.validFor',
                'T0.frozenFor',
                'T0.InvntryUom',
                'T0.CodeBars',
            ])
            ->get();

        return $this->syncRows($this->normalizeStockRows($rows));
    }

    private function normalizeStockRows(Collection $rows): Collection
    {
        return $rows
            ->filter(fn ($row) => isset($row->ItemCode) && trim((string) $row->ItemCode) !== '')
            ->groupBy(fn ($row) => trim((string) $row->ItemCode))
            ->map(function (Collection $itemRows): object {
                $first = $itemRows->first();

                $warehouses = [];

                foreach ($itemRows as $row) {
                    $code = strtoupper(trim((string) ($row->Bodega ?? '')));

                    if ($code !== '') {
                        $warehouses[$code] = round((float) ($row->Stock ?? 0), 4);
                    }
                }

                $central = 0.0;

                foreach (self::CENTRAL_WAREHOUSES as $warehouse) {
                    $central += $warehouses[$warehouse] ?? 0.0;
                }

                $production = $warehouses[self::PRODUCTION_WAREHOUSE] ?? 0.0;

                return (object) [
                    'ItemCode' => trim((string) $first->ItemCode),
                    'ItemName' => trim((string) ($first->ItemName ?? '')),
                    'ItmsGrpCod' => $first->ItmsGrpCod ?? null,
                    'InvntItem' => $first->InvntItem ?? null,
                    'AvgPrice' => $first->AvgPrice ?? null,
                    'validFor' => $first->validFor ?? null,
                    'frozenFor' => $first->frozenFor ?? null,
                    'InvntryUom' => $first->InvntryUom ?? null,
                    'CodeBars' => $first->CodeBars ?? null,
                    'CreateDate' => $this->normalizeSapDate($first->CreateDate ?? null),
                    'UpdateDate' => $this->normalizeSapDate($first->UpdateDate ?? null),
                    'OnHand' => round($central + $production, 4),
                    'CentralStock' => round($central, 4),
                    'ProductionStock' => round($production, 4),
                    'Warehouses' => $warehouses,
                ];
            })
            ->values();
    }

    private function normalizeSapDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Ymd');
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->format('Ymd');
    }

    private function syncRows(Collection $rows): array
    {
        $rows = $rows->keyBy(fn ($row) => (string) $row->ItemCode);

        $created = 0;
        $updated = 0;
        $zeroed = 0;

        DB::transaction(function () use ($rows, &$created, &$updated, &$zeroed): void {
            $allMaterials = InventoryMaterial::query()->get()->keyBy('codigo');
            $ownMaterials = $allMaterials->filter(fn (InventoryMaterial $material) => $material->service_id === null);

            $presentCodes = [];

            foreach ($rows as $row) {
                $code = trim((string) ($row->ItemCode ?? ''));
                $name = trim((string) ($row->ItemName ?? ''));

                if ($code === '' || $name === '') {
                    continue;
                }

                $presentCodes[$code] = true;

                $existingMaterial = $allMaterials->get($code);

                if ($existingMaterial && $existingMaterial->service_id !== null) {
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

                $material = $ownMaterials->get($code);

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
                    'metadata' => array_merge($material?->metadata ?? [], [
                        'sap' => [
                            'code_bars' => $row->CodeBars ?? null,
                            'create_date' => $row->CreateDate ?? null,
                            'update_date' => $row->UpdateDate ?? null,
                            'indirect_tax' => null,
                            'obj_type' => '4',
                            'warehouses' => $row->Warehouses ?? [],
                            'central_stock' => (float) ($row->CentralStock ?? 0),
                            'production_stock' => (float) ($row->ProductionStock ?? 0),
                        ],
                    ]),
                ];

                if ($material) {
                    $material->fill($payload)->save();
                    $updated++;

                    continue;
                }

                InventoryMaterial::create(array_merge(['codigo' => $code], $payload));
                $created++;
            }

            foreach ($ownMaterials as $code => $material) {
                if (isset($presentCodes[$code])) {
                    continue;
                }

                $material->fill([
                    'sap_on_hand' => 0,
                    'metadata' => array_merge($material->metadata ?? [], [
                        'sap' => array_merge($material->metadata['sap'] ?? [], [
                            'warehouses' => [],
                            'central_stock' => 0.0,
                            'production_stock' => 0.0,
                        ]),
                    ]),
                ])->save();

                $zeroed++;
            }
        });

        return [
            'total' => $rows->count(),
            'created' => $created,
            'updated' => $updated,
            'zeroed' => $zeroed,
        ];
    }
}
