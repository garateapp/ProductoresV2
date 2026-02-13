<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('process_lots')) {
            return;
        }

        Schema::table('process_lots', function (Blueprint $table) {
            if (! Schema::hasColumn('process_lots', 'source_type')) {
                $table->string('source_type', 24)->nullable()->after('n_g_recepcion');
            }
            if (! Schema::hasColumn('process_lots', 'source_key')) {
                $table->string('source_key', 120)->nullable()->after('source_type');
            }
            if (! Schema::hasColumn('process_lots', 'source_folio')) {
                $table->string('source_folio', 120)->nullable()->after('source_key');
            }
            if (! Schema::hasColumn('process_lots', 'source_n_g_proceso')) {
                $table->string('source_n_g_proceso', 120)->nullable()->after('source_folio');
            }
            if (! Schema::hasColumn('process_lots', 'source_lote')) {
                $table->string('source_lote', 120)->nullable()->after('source_n_g_proceso');
            }
            if (! Schema::hasColumn('process_lots', 'source_c_embalaje')) {
                $table->string('source_c_embalaje', 60)->nullable()->after('source_lote');
            }
            if (! Schema::hasColumn('process_lots', 'source_n_embalaje')) {
                $table->string('source_n_embalaje', 160)->nullable()->after('source_c_embalaje');
            }
            if (! Schema::hasColumn('process_lots', 'source_categoria')) {
                $table->string('source_categoria', 120)->nullable()->after('source_n_embalaje');
            }
            if (! Schema::hasColumn('process_lots', 'source_snapshot')) {
                $table->json('source_snapshot')->nullable()->after('source_categoria');
            }
        });

        Schema::table('process_lots', function (Blueprint $table) {
            try {
                $table->index(['source_type', 'source_key'], 'idx_process_lots_source_key');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('process_lots')) {
            return;
        }

        Schema::table('process_lots', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_process_lots_source_key');
            } catch (\Throwable $e) {
                // ignore
            }

            $toDrop = [
                'source_type',
                'source_key',
                'source_folio',
                'source_n_g_proceso',
                'source_lote',
                'source_c_embalaje',
                'source_n_embalaje',
                'source_categoria',
                'source_snapshot',
            ];

            $existing = [];
            foreach ($toDrop as $col) {
                if (Schema::hasColumn('process_lots', $col)) {
                    $existing[] = $col;
                }
            }
            if (! empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};

