<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimation_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('estimation_versions', 'species')) {
                $table->string('species', 32)->nullable()->after('type');
                $table->index(['season_id', 'species', 'status'], 'idx_estimation_versions_season_species_status');
            }
        });

        Schema::table('estimation_rows', function (Blueprint $table) {
            if (! Schema::hasColumn('estimation_rows', 'variedad_rotulada')) {
                $table->string('variedad_rotulada', 191)->nullable()->after('variedad_id');
            }
            if (! Schema::hasColumn('estimation_rows', 'planta')) {
                $table->string('planta', 120)->nullable()->after('variedad_rotulada');
            }
            if (! Schema::hasColumn('estimation_rows', 'mexico')) {
                $table->boolean('mexico')->nullable()->after('planta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimation_rows', function (Blueprint $table) {
            if (Schema::hasColumn('estimation_rows', 'mexico')) {
                $table->dropColumn('mexico');
            }
            if (Schema::hasColumn('estimation_rows', 'planta')) {
                $table->dropColumn('planta');
            }
            if (Schema::hasColumn('estimation_rows', 'variedad_rotulada')) {
                $table->dropColumn('variedad_rotulada');
            }
        });

        Schema::table('estimation_versions', function (Blueprint $table) {
            if (Schema::hasColumn('estimation_versions', 'species')) {
                $table->dropIndex('idx_estimation_versions_season_species_status');
                $table->dropColumn('species');
            }
        });
    }
};

