<?php

namespace App\Services\PreCooling;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FolioProvider
{
    public function buscar(string $folio): ?array
    {
        $view = (string) config('services.termo.sqlsrv_view', 'V_PKG_Produccion_Salidas_XXX');

        try {
            $rows = DB::connection('sqlsrv')->select(
                <<<SQL
                SELECT n_exportadora as exportadora,
                       folio as folio,
                       n_productor as productor,
                       n_especie as especie,
                       n_variedad as variedad,
                       n_embalaje as embalaje,
                       n_categoria as categoria,
                       n_calibre as calibre,
                       SUM(cantidad) as cantidad
                  FROM {$view}
                 WHERE folio = ?
                 GROUP BY n_exportadora, folio, n_productor, n_especie, n_variedad, n_embalaje, n_categoria, n_calibre
                SQL,
                [$folio]
            );
        } catch (\Throwable $e) {
            Log::error('PRECOOLING_FOLIO_LOOKUP_ERROR', [
                'folio' => $folio,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (empty($rows)) {
            return null;
        }

        $row = $rows[0];

        return [
            'folio' => $row->folio ?? $folio,
            'exportadora' => $row->exportadora ?? null,
            'productor' => $row->productor ?? null,
            'especie' => $row->especie ?? null,
            'variedad' => $row->variedad ?? null,
            'embalaje' => $row->embalaje ?? null,
            'categoria' => $row->categoria ?? null,
            'calibre' => $row->calibre ?? null,
            'cajas' => (int) ($row->cantidad ?? 0),
            'metadata' => (array) $row,
        ];
    }
}
