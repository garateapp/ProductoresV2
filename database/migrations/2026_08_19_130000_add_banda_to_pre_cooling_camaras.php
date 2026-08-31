<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cooling_saldos', function (Blueprint $table) {
            $table->index('camara_id', 'pcs_camara_id_index');
        });

        Schema::table('pre_cooling_saldos', function (Blueprint $table) {
            $table->dropUnique(['camara_id', 'fila', 'columna', 'altura', 'nivel']);
            $table->string('banda', 50)->default('B1')->after('camara_id');
            $table->unique(
                ['camara_id', 'banda', 'fila', 'columna', 'altura', 'nivel'],
                'pcs_camara_slot_unique'
            );
        });

        $now = now();
        foreach (DB::table('pre_cooling_camaras')->pluck('id') as $camaraId) {
            $exists = DB::table('pre_cooling_camara_parametros')
                ->where('camara_id', $camaraId)
                ->where('dimension', 'banda')
                ->exists();

            if (! $exists) {
                DB::table('pre_cooling_camara_parametros')->insert([
                    'camara_id' => $camaraId,
                    'dimension' => 'banda',
                    'valor' => 'B1',
                    'orden' => 0,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('pre_cooling_camara_parametros')->where('dimension', 'banda')->delete();

        Schema::table('pre_cooling_saldos', function (Blueprint $table) {
            $table->dropUnique('pcs_camara_slot_unique');
            $table->dropColumn('banda');
            $table->unique(['camara_id', 'fila', 'columna', 'altura', 'nivel']);
        });

        Schema::table('pre_cooling_saldos', function (Blueprint $table) {
            $table->dropIndex('pcs_camara_id_index');
        });
    }
};
