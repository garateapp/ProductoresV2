<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProcessedFruitQualityExport implements FromCollection, WithHeadings, WithMapping
{
    protected $items;

    public function __construct(Collection $items)
    {
        $this->items = $items;
    }

    public function collection()
    {
        return $this->items;
    }

    public function headings(): array
    {
        return [
            'Proceso', 'Fecha', 'N° Caja', 'Estado', 'Responsable',
            'Tamaño Muestra', 'Embaladora Mano', 'Peso Exacto Caja', 'Código Embalaje', 'Categoría', 'Destino',
            'Calibre', 'Color Cubrimiento', 'Color Fondo', 'Observaciones',
        ];
    }

    public function map($q): array
    {
        return [
            optional($q->proceso)->n_proceso,
            optional($q->proceso)->fecha,
            $q->numero_de_caja,
            $q->estado,
            $q->responsable,
            $q->t_muestra,
            $q->numero_embaladora_mano,
            $q->peso_exacto_caja,
            $q->codigo_embalaje,
            $q->categoria,
            $q->destino,
            $q->calibre,
            $q->color_cubrimiento,
            $q->color_fondo,
            $q->observaciones,
        ];
    }
}
