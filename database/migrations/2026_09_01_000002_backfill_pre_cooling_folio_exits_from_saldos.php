<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE pre_cooling_load_folios AS folio
            INNER JOIN pre_cooling_saldos AS saldo ON saldo.load_folio_id = folio.id
            INNER JOIN pre_cooling_loads AS carga ON carga.id = folio.load_id
            SET folio.camara_destino_id = COALESCE(folio.camara_destino_id, saldo.camara_id),
                folio.fecha_hora_salida = COALESCE(folio.fecha_hora_salida, carga.fecha_hora_fin, saldo.created_at),
                folio.temperatura_ambiente_tunel_salida = COALESCE(
                    folio.temperatura_ambiente_tunel_salida,
                    carga.temperatura_ambiente_final
                ),
                folio.usuario_salida_id = COALESCE(folio.usuario_salida_id, carga.usuario_fin_id, saldo.usuario_id)
            WHERE folio.fecha_hora_salida IS NULL
        SQL);
    }

    public function down(): void
    {
        // El backfill completa trazabilidad histórica; no se borra información al revertir.
    }
};
