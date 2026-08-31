<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bands = ['Izquierda', 'Central-Izq', 'Central-Dcha', 'Derecha'];
        $now = now();

        foreach (DB::table('pre_cooling_camaras')->pluck('id') as $camaraId) {
            $legacyBand = DB::table('pre_cooling_camara_parametros')
                ->where('camara_id', $camaraId)
                ->where('dimension', 'banda')
                ->where('valor', 'B1');

            if ($legacyBand->exists()) {
                $hasLeftBand = DB::table('pre_cooling_camara_parametros')
                    ->where('camara_id', $camaraId)
                    ->where('dimension', 'banda')
                    ->where('valor', 'Izquierda')
                    ->exists();

                DB::table('pre_cooling_saldos')
                    ->where('camara_id', $camaraId)
                    ->where('banda', 'B1')
                    ->update(['banda' => 'Izquierda']);

                if ($hasLeftBand) {
                    $legacyBand->delete();
                } else {
                    $legacyBand->update(['valor' => 'Izquierda', 'updated_at' => $now]);
                }
            }

            foreach ($bands as $order => $band) {
                $existingBand = DB::table('pre_cooling_camara_parametros')
                    ->where('camara_id', $camaraId)
                    ->where('dimension', 'banda')
                    ->where('valor', $band);

                if ($existingBand->exists()) {
                    $existingBand->update([
                        'orden' => $order,
                        'activo' => true,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('pre_cooling_camara_parametros')->insert([
                        'camara_id' => $camaraId,
                        'dimension' => 'banda',
                        'valor' => $band,
                        'orden' => $order,
                        'activo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Se conserva la jerarquía para no invalidar ubicaciones de inventario existentes.
    }
};
