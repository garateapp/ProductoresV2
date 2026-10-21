<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithProperties;

class PersonDeliveryReportExport implements WithMultipleSheets, WithProperties
{
    public const SHEET_CONSOLIDATED = 'Consolidado por Área';

    public const SHEET_DETAIL = 'Detalle';

    /**
     * @param  array<int, array<string, mixed>>  $consolidatedRows
     * @param  array<int, array<string, mixed>>  $detailRows
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private readonly array $consolidatedRows,
        private readonly array $detailRows,
        private readonly array $meta = [],
    ) {
    }

    public function sheets(): array
    {
        return [
            self::SHEET_CONSOLIDATED => new PersonDeliveryConsolidatedSheet($this->consolidatedRows, $this->meta),
            self::SHEET_DETAIL => new PersonDeliveryDetailSheet($this->detailRows, $this->meta),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function properties(): array
    {
        return [
            'creator' => 'GreenEx',
            'title' => 'Reporte de entregas de materiales a personas',
            'subject' => 'Consolidado por área y detalle de entregas',
            'description' => 'Entregas de materiales a personas con filtros por fecha, material, área y persona.',
        ];
    }
}
