<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'reserved_bins')) {
                $table->unsignedInteger('reserved_bins')->default(0)->after('estado');
            }
        });

        // Backfill best-effort desde los lotes confirmados (si existen).
        // Esto evita que las reservas antiguas queden con 0 bins reservados.
        try {
            if (Schema::hasTable('process_lots') && Schema::hasColumn('reservations', 'reserved_bins')) {
                DB::statement("
                    UPDATE reservations
                    SET reserved_bins = (
                        SELECT COALESCE(SUM(pl.cantidad_bins), 0)
                        FROM process_lots pl
                        WHERE pl.process_id = reservations.process_id
                          AND pl.n_g_recepcion = reservations.n_g_recepcion
                    )
                ");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Cambiamos el esquema para permitir saldo:
        // - Antes: unique(n_g_recepcion) => bloquea planificar el mismo lote en otro turno/día.
        // - Ahora: unique(n_g_recepcion, process_id) + reserved_bins para evitar sobre-reserva.
        Schema::table('reservations', function (Blueprint $table) {
            try {
                $table->dropUnique('reservations_n_g_recepcion_unique');
            } catch (\Throwable $e) {
                // ignore (ya no existe o nombre distinto)
            }

            try {
                $table->unique(['n_g_recepcion', 'process_id'], 'uq_reservations_lot_process');
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                $table->index(['n_g_recepcion'], 'idx_reservations_lot');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_reservations_lot');
            } catch (\Throwable $e) {
                // ignore
            }
            try {
                $table->dropUnique('uq_reservations_lot_process');
            } catch (\Throwable $e) {
                // ignore
            }
            try {
                $table->unique('n_g_recepcion');
            } catch (\Throwable $e) {
                // ignore
            }

            if (Schema::hasColumn('reservations', 'reserved_bins')) {
                $table->dropColumn('reserved_bins');
            }
        });
    }
};
