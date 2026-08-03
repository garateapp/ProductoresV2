<?php

namespace App\Services\Inventory;

use App\Models\InventoryMaterial;
use App\Models\InventoryMaterialFamily;
use App\Models\InventoryUnit;
use App\Models\SapSyncState;
use App\Services\Sap\ServiceLayerClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaterialCatalogService
{
    private const SAP_GROUP_CODES = [118,127, 139, 312, 142, 150, 140, 444, 405, 124, 141, 129, 102, 130, 311, 313, 327, 329, 331, 400, 401, 403, 413,421, 438, 458, 459];

    private const LAST_SYNC_DATE_KEY = 'materials.last_sync_date';

    private const SQL_QUERY_NAME = 'vGEX_OITM';

    public function __construct(
        private readonly ServiceLayerClient $serviceLayerClient,
    ) {}

    public function syncFromSap(?string $desde = null, ?string $hasta = null): array
    {
        if (config('services.sap_service_layer.enabled')) {
            return $this->syncFromServiceLayer($desde, $hasta);
        }

        return $this->syncFromSql();
    }

    private function syncFromServiceLayer(?string $desde, ?string $hasta): array
    {
        $hastaDate = $hasta !== null ? $this->parseDate($hasta, 'hasta') : Carbon::today();

        $desdeDate = $desde !== null
            ? $this->parseDate($desde, 'desde')
            : $this->resolveDesdeDate($hastaDate);

        $rows = collect();

        for ($date = $desdeDate->copy(); $date->lte($hastaDate); $date->addDay()) {
            $rows = $rows->merge($this->serviceLayerClient->sqlQuery(
                self::SQL_QUERY_NAME,
                ['Fecha' => "'".$date->format('Ymd')."'"]
            ));
        }

        $rows = $rows
            ->filter(fn ($row) => isset($row->ItemCode) && trim((string) $row->ItemCode) !== '')
            ->keyBy(fn ($row) => (string) $row->ItemCode)
            ->values();

        $summary = $this->syncRows($rows);

        SapSyncState::set(self::LAST_SYNC_DATE_KEY, $hastaDate->format('Ymd'));

        return $summary;
    }

    private function resolveDesdeDate(Carbon $hastaDate): Carbon
    {
        $lastSync = SapSyncState::get(self::LAST_SYNC_DATE_KEY);

        if ($lastSync !== null && preg_match('/^\d{8}$/', $lastSync) === 1) {
            return Carbon::createFromFormat('Ymd', $lastSync);
        }

        return $hastaDate->copy()->subDays((int) config('services.sap_service_layer.default_days_back', 30));
    }

    private function parseDate(string $value, string $label): Carbon
    {
        if (preg_match('/^\d{8}$/', $value) !== 1) {
            throw new InvalidArgumentException("El parámetro '{$label}' debe tener formato YYYYMMDD.");
        }

        return Carbon::createFromFormat('Ymd', $value);
    }

    private function syncFromSql(): array
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

        return $this->syncRows($rows);
    }

    private function syncRows(Collection $rows): array
    {
        $rows = $rows
            ->filter(fn ($row) => in_array((int) ($row->ItmsGrpCod ?? -1), self::SAP_GROUP_CODES, true))
            ->values();

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
