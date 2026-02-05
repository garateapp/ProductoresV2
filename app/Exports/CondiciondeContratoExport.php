<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CondiciondeContratoExport implements FromCollection, WithHeadings, WithMapping
{
    protected Collection $logs;



    public function __construct(Collection $logs, ?string $typeFilter = null)
    {
        $this->logs = $logs;

    }

    public function collection(): Collection
    {
        return $this->logs;
    }

    public function headings(): array
    {


        return [
            'Productor',
            'Comision',
            'Flete a Huerto',
            'Desc. Hidrocooler',
            'Rebate',
            'Bonificación',
            'Tarifa Premium',
            'Comparativa',
            'Descuento Fruta Comercial',
            '% Desc. Comercial'
        ];
    }

    public function map($log): array
    {


        return [
            $log->user->name ?? 'N/A',
            $log->comision,
            $log->flete_a_huerto,
            $log->descuento_hidrocooler,
            $log->rebate,
            $log->bonificacion,
            $log->tarifa_premium,
            $log->comparativa,
            $log->descuento_fruta_comercial,
            $log->porcentaje_descuento
        ];
    }

    protected function isWhatsappType(?string $type): bool
    {
        return in_array(strtolower((string) $type), ['whatsapp', 'whtsapp'], true);
    }
}
