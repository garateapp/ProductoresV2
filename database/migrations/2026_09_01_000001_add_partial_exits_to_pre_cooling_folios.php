<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->index('tipo_proceso_id', 'pc_folios_tipo_proceso_idx');
        });

        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->dropUnique('pre_cooling_load_folios_tipo_proceso_id_folio_unique');
            $table->foreignId('camara_destino_id')->nullable()->after('load_id')
                ->constrained('pre_cooling_camaras')->nullOnDelete();
            $table->dateTime('fecha_hora_salida')->nullable()->after('temperatura_final_externa');
            $table->decimal('temperatura_ambiente_tunel_salida', 5, 2)->nullable()->after('fecha_hora_salida');
            $table->decimal('temperatura_ambiente_camara_salida', 5, 2)->nullable()->after('temperatura_ambiente_tunel_salida');
            $table->foreignId('usuario_salida_id')->nullable()->after('temperatura_ambiente_camara_salida')
                ->constrained('users')->nullOnDelete();
            $table->index(['load_id', 'fecha_hora_salida'], 'pc_folios_load_salida_idx');
        });

        Schema::table('pre_cooling_saldos', function (Blueprint $table) {
            $table->foreignId('load_folio_id')->nullable()->after('id')
                ->constrained('pre_cooling_load_folios')->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE pre_cooling_load_folios AS folio
            INNER JOIN pre_cooling_loads AS carga ON carga.id = folio.load_id
            SET folio.camara_destino_id = carga.camara_destino_id,
                folio.fecha_hora_salida = carga.fecha_hora_fin,
                folio.temperatura_ambiente_tunel_salida = carga.temperatura_ambiente_final,
                folio.usuario_salida_id = carga.usuario_fin_id
            WHERE carga.estado = 'salido'
              AND carga.fecha_hora_fin IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE pre_cooling_saldos AS saldo
            INNER JOIN pre_cooling_load_folios AS folio
                ON folio.folio = saldo.folio
               AND folio.tipo_proceso_id = saldo.tipo_proceso_id
            SET saldo.load_folio_id = folio.id
            WHERE saldo.load_folio_id IS NULL
        SQL);

        Schema::table('pre_cooling_saldos', function (Blueprint $table) {
            $table->unique('load_folio_id', 'pc_saldos_load_folio_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pre_cooling_saldos', function (Blueprint $table) {
            $table->dropUnique('pc_saldos_load_folio_unique');
            $table->dropConstrainedForeignId('load_folio_id');
        });

        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->dropIndex('pc_folios_load_salida_idx');
            $table->dropConstrainedForeignId('camara_destino_id');
            $table->dropConstrainedForeignId('usuario_salida_id');
            $table->dropColumn([
                'fecha_hora_salida',
                'temperatura_ambiente_tunel_salida',
                'temperatura_ambiente_camara_salida',
            ]);
            $table->unique(['tipo_proceso_id', 'folio']);
        });

        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->dropIndex('pc_folios_tipo_proceso_idx');
        });
    }
};
