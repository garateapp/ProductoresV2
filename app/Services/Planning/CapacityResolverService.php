<?php

namespace App\Services\Planning;

use App\Models\LineCapacity;
use Carbon\CarbonInterface;

class CapacityResolverService
{
    /**
     * Resuelve bins_por_hora para una línea, especie y turno (si aplica) en una fecha dada.
     */
    public function resolveBinsPorHora(int $packingLineId, string $especie, ?int $shiftId, CarbonInterface $fecha): ?float
    {
        $query = LineCapacity::query()
            ->where('packing_line_id', $packingLineId)
            ->where('especie', $especie)
            ->where('activo', true)
            ->whereDate('vigencia_desde', '<=', $fecha)
            ->where(function ($sub) use ($fecha) {
                $sub->whereNull('vigencia_hasta')->orWhereDate('vigencia_hasta', '>=', $fecha);
            })
            ->orderByDesc('vigencia_desde');

        // Preferimos capacidad específica del turno, si existe.
        if ($shiftId !== null) {
            $withShift = (clone $query)->where('shift_id', $shiftId)->first();
            if ($withShift) {
                return (float) $withShift->bins_por_hora;
            }
        }

        $generic = $query->whereNull('shift_id')->first();
        return $generic ? (float) $generic->bins_por_hora : null;
    }
}

