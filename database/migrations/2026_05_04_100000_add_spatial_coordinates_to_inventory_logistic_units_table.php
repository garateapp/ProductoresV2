<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_logistic_units', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_logistic_units', 'spatial_column')) {
                $table->string('spatial_column', 50)->nullable()->after('current_location_id');
            }

            if (! Schema::hasColumn('inventory_logistic_units', 'spatial_row')) {
                $table->string('spatial_row', 50)->nullable()->after('spatial_column');
            }

            if (! Schema::hasIndex('inventory_logistic_units', 'idx_inventory_logistic_units_spatial_position')) {
                $table->index(['current_location_id', 'spatial_column', 'spatial_row'], 'idx_inventory_logistic_units_spatial_position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_logistic_units', function (Blueprint $table): void {
            if (Schema::hasIndex('inventory_logistic_units', 'idx_inventory_logistic_units_spatial_position')) {
                $table->dropIndex('idx_inventory_logistic_units_spatial_position');
            }

            if (Schema::hasColumn('inventory_logistic_units', 'spatial_row')) {
                $table->dropColumn('spatial_row');
            }

            if (Schema::hasColumn('inventory_logistic_units', 'spatial_column')) {
                $table->dropColumn('spatial_column');
            }
        });
    }
};
