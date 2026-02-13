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
            if (! Schema::hasColumn('reservations', 'source_type')) {
                $table->string('source_type', 24)->default('recepcion')->after('n_g_recepcion');
            }
            if (! Schema::hasColumn('reservations', 'source_key')) {
                $table->string('source_key', 120)->nullable()->after('source_type');
            }
        });

        try {
            if (Schema::hasColumn('reservations', 'source_type') && Schema::hasColumn('reservations', 'source_key')) {
                DB::table('reservations')
                    ->whereNull('source_key')
                    ->update([
                        'source_type' => 'recepcion',
                        'source_key' => DB::raw('n_g_recepcion'),
                    ]);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Reemplaza llave antigua por llave por origen (recepción/reembalaje + clave).
        $this->dropIndexIfExists('reservations', 'uq_reservations_lot_process');
        $this->dropIndexIfExists('reservations', 'reservations_n_g_recepcion_unique');
        $this->dropIndexIfExists('reservations', 'reservations_n_g_recepcion_process_id_unique');

        $this->addUniqueIfMissing('reservations', ['source_type', 'source_key', 'process_id'], 'uq_reservations_source_process');
        $this->addIndexIfMissing('reservations', ['source_type', 'source_key'], 'idx_reservations_source');
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        $this->dropIndexIfExists('reservations', 'idx_reservations_source');
        $this->dropIndexIfExists('reservations', 'uq_reservations_source_process');
        $this->addUniqueIfMissing('reservations', ['n_g_recepcion', 'process_id'], 'uq_reservations_lot_process');

        $toDrop = [];
        if (Schema::hasColumn('reservations', 'source_type')) {
            $toDrop[] = 'source_type';
        }
        if (Schema::hasColumn('reservations', 'source_key')) {
            $toDrop[] = 'source_key';
        }
        if (! empty($toDrop)) {
            Schema::table('reservations', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }

    private function addUniqueIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                $table->unique($columns, $indexName);
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            return;
        }

        try {
            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP INDEX `%s`',
                str_replace('`', '``', $tableName),
                str_replace('`', '``', $indexName)
            ));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver !== 'mysql') {
                return false;
            }

            $table = str_replace('`', '``', $tableName);
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return ! empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
