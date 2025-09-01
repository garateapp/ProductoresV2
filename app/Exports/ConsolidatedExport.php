<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use App\Models\ProcessedFruitQuality;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ConsolidatedExport implements FromCollection, WithHeadings, WithMapping
{
    protected $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return $this->data;
    }

    /**
     * @var ProcessedFruitQuality $processedFruitQuality
     */
    public function map($processedFruitQuality): array
    {
        $calibre = '';
        $cantidadCalibre = '';

        foreach ($processedFruitQuality->details as $detail) {
            if ($detail->parametro && strtolower($detail->parametro->name) === 'calibre') {
                $calibre = $detail->valor->nombre ?? '';
                $cantidadCalibre = $detail->cantidad ?? '';
                break;
            }
        }

        return [
            $processedFruitQuality->proceso->lote ?? '',
            $processedFruitQuality->fecha ?? '',
            $processedFruitQuality->reception->producer->nombre ?? '',
            $processedFruitQuality->reception->specie->nombre ?? '',
            $processedFruitQuality->reception->variety->nombre ?? '',
            $calibre,
            $cantidadCalibre,
            // Add other fields as needed, matching the headings order
            // Placeholder for other fields, you'll need to map them correctly
            '', // Firmeza Promedio
            '', // Distribución Firmeza
            '', // Sólidos Solubles
            '', // Color Cubrimiento
            '', // Defectos Calidad
            '', // Defectos Condición
            '', // Daño Plaga
        ];
    }

    public function headings(): array
    {
        // Define your headings here based on the consolidated data structure
        // This is a placeholder, you'll need to adjust it based on the actual data
        return [
            'Lote',
            'Fecha Recepción',
            'Productor',
            'Especie',
            'Variedad',
            'Calibre',
            'Cantidad Calibre',
            'Firmeza Promedio',
            'Distribución Firmeza',
            'Sólidos Solubles',
            'Color Cubrimiento',
            'Defectos Calidad',
            'Defectos Condición',
            'Daño Plaga',
        ];
    }
}
