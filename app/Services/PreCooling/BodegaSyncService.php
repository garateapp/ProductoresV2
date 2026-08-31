<?php

namespace App\Services\PreCooling;

use App\Models\PreCoolingBodega;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BodegaSyncService
{
    public function sync(): array
    {
        $rows = DB::connection('sqlsrv')->select("
            SELECT codigo, nombre, filas, columnas, alto_maximo,
                   capacidad, Tipo_Posiciones, Pos_X, Pos_Y
            FROM ADM_P_Bodegas
            WHERE codigo IS NOT NULL
            ORDER BY codigo
        ");

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $codigo = trim($row->codigo);
            if (empty($codigo)) {
                $skipped++;
                continue;
            }

            $data = [
                'nombre' => trim($row->nombre ?? $codigo),
                'filas' => (int) ($row->filas ?? 0),
                'columnas' => (int) ($row->columnas ?? 0),
                'alto_maximo' => (int) ($row->alto_maximo ?? 0),
                'capacidad' => (int) ($row->capacidad ?? 0),
                'tipo_posiciones' => trim($row->Tipo_Posiciones ?? ''),
                'pos_x' => (float) ($row->Pos_X ?? 0),
                'pos_y' => (float) ($row->Pos_Y ?? 0),
                'activo' => true,
            ];

            $existing = PreCoolingBodega::where('codigo', $codigo)->first();

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                PreCoolingBodega::create(array_merge($data, ['codigo' => $codigo]));
                $created++;
            }
        }

        $total = $created + $updated + $skipped;

        Log::info("BodegaSync completed", compact('created', 'updated', 'skipped', 'total'));

        return compact('created', 'updated', 'skipped', 'total');
    }
}
