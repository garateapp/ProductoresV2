<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimation_biweekly_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('estimation_biweekly_versions', 'origin')) {
                $table->string('origin', 32)
                    ->default('agronomo')
                    ->after('season_id');
                $table->index(
                    ['season_id', 'origin', 'status'],
                    'idx_est_biweekly_season_origin_status'
                );
            }
        });

        Schema::table('estimation_biweekly_rows', function (Blueprint $table) {
            if (! Schema::hasColumn('estimation_biweekly_rows', 'service_id')) {
                $table->foreignId('service_id')
                    ->nullable()
                    ->after('producer_id')
                    ->constrained('services')
                    ->nullOnDelete();
                $table->index(['service_id', 'dia'], 'idx_est_biweekly_rows_service_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimation_biweekly_rows', function (Blueprint $table) {
            if (Schema::hasColumn('estimation_biweekly_rows', 'service_id')) {
                $table->dropIndex('idx_est_biweekly_rows_service_day');
                $table->dropConstrainedForeignId('service_id');
            }
        });

        Schema::table('estimation_biweekly_versions', function (Blueprint $table) {
            if (Schema::hasColumn('estimation_biweekly_versions', 'origin')) {
                $table->dropIndex('idx_est_biweekly_season_origin_status');
                $table->dropColumn('origin');
            }
        });
    }
};
