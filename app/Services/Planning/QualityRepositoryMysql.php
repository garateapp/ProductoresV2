<?php

namespace App\Services\Planning;

use App\Models\Recepcion;
use Illuminate\Support\Collection;

class QualityRepositoryMysql
{
    /**
     * Retorna snapshot de calidad por n_g_recepcion (MySQL, SOLO LECTURA).
     *
     * Unión clave:
     * - Recepcion.numero_g_recepcion = SQLSRV.V_PKG_Stock_Inventario.n_g_recepcion
     *
     * Reglas de extracción:
     * - Calibre: Detalle.tipo_item = 'DISTRIBUCIÓN DE CALIBRES' → Detalle.detalle_item
     * - Color:   Detalle.tipo_item = 'COLOR DE CUBRIMIENTO' → Detalle.detalle_item
     * - Brix:    Detalle.tipo_item = 'SOLIDOS SOLUBLES' → Detalle.valor_ss
     *
     * Si no hay match: setear todo en null y marcar warning.
     */
    public function getQualityByNGRecepcion(array $nGRecepcions): array
    {
        $normalized = collect($nGRecepcions)
            ->filter()
            ->map(fn ($n) => (string) $n)
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return [];
        }

        $recepciones = Recepcion::query()
            ->whereIn('numero_g_recepcion', $normalized->all())
            ->with(['calidad.detalles'])
            ->get()
            ->keyBy(fn ($r) => (string) $r->numero_g_recepcion);

        $result = [];
        foreach ($normalized as $n) {
            $recepcion = $recepciones->get($n);
            if (! $recepcion) {
                $result[$n] = [
                    'setup_nota_calidad' => null,
                    'setup_calibre' => null,
                    'setup_color' => null,
                    'brix' => null,
                    'warning' => true,
                ];
                continue;
            }

            $nota = $recepcion->nota_calidad;
            $notaStr = $nota === null ? null : ((int) $nota === 0 ? 'S/N' : (string) $nota);

            $detalles = $recepcion->calidad?->detalles ?? collect();
            if (! $detalles instanceof Collection) {
                $detalles = collect($detalles);
            }

            $calibre = $this->pickDetalleItem($detalles, 'DISTRIBUCIÓN DE CALIBRES');
            $color = $this->pickDetalleItem($detalles, 'COLOR DE CUBRIMIENTO');
            $brix = $this->pickBrix($detalles);

            $result[$n] = [
                'setup_nota_calidad' => $notaStr,
                'setup_calibre' => $calibre,
                'setup_color' => $color,
                'brix' => $brix,
                'warning' => $calibre === null && $color === null && $brix === null && $notaStr === null,
            ];
        }

        return $result;
    }

    private function pickDetalleItem(Collection $detalles, string $tipoItem): ?string
    {
        $candidate = $detalles
            ->filter(fn ($d) => mb_strtoupper(trim((string) ($d->tipo_item ?? ''))) === $tipoItem)
            ->sortByDesc(fn ($d) => (float) ($d->porcentaje_muestra ?? 0))
            ->first();

        $value = $candidate?->detalle_item;
        $value = $value === null ? null : trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function pickBrix(Collection $detalles): ?float
    {
        $candidate = $detalles
            ->filter(fn ($d) => mb_strtoupper(trim((string) ($d->tipo_item ?? ''))) === 'SOLIDOS SOLUBLES')
            ->sortByDesc(fn ($d) => (float) ($d->porcentaje_muestra ?? 0))
            ->first();

        $value = $candidate?->valor_ss;
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
