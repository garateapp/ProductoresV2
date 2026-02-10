<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimation_biweekly_rows', function (Blueprint $table) {
            $table->index('estimation_biweekly_version_id', 'idx_est_biweekly_rows_version');
            $table->dropUnique('uq_estimation_biweekly_rows_key');
            $table->unique(
                ['estimation_biweekly_version_id', 'producer_id', 'sucursal', 'variedad_id', 'dia', 'total_kilo'],
                'uq_estimation_biweekly_rows_key'
            );
        });
    }

    public function down(): void
    {
        Schema::table('estimation_biweekly_rows', function (Blueprint $table) {
            $table->dropUnique('uq_estimation_biweekly_rows_key');
            $table->unique(
                ['estimation_biweekly_version_id', 'producer_id', 'sucursal', 'variedad_id', 'semana', 'dia'],
                'uq_estimation_biweekly_rows_key'
            );
            $table->dropIndex('idx_est_biweekly_rows_version');
        });
    }
};
