<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $dimensions = [
            'fila_izquierda',
            'fila_central_izq',
            'fila_central_dcha',
            'fila_derecha',
        ];
        $now = now();

        foreach (DB::table('pre_cooling_camaras')->pluck('id') as $camaraId) {
            $legacyRows = DB::table('pre_cooling_camara_parametros')
                ->where('camara_id', $camaraId)
                ->where('dimension', 'fila')
                ->where('activo', true)
                ->orderBy('orden')
                ->pluck('valor')
                ->all();

            if (empty($legacyRows)) {
                $legacyRows = ['1'];
            }

            foreach ($dimensions as $dimension) {
                $hasRows = DB::table('pre_cooling_camara_parametros')
                    ->where('camara_id', $camaraId)
                    ->where('dimension', $dimension)
                    ->exists();

                if ($hasRows) {
                    continue;
                }

                foreach ($legacyRows as $order => $row) {
                    DB::table('pre_cooling_camara_parametros')->insert([
                        'camara_id' => $camaraId,
                        'dimension' => $dimension,
                        'valor' => $row,
                        'orden' => $order,
                        'activo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            DB::table('pre_cooling_camara_parametros')
                ->where('camara_id', $camaraId)
                ->where('dimension', 'fila')
                ->delete();
        }
    }

    public function down(): void
    {
        // Se mantienen las filas por banda para no invalidar ubicaciones existentes.
    }
};
