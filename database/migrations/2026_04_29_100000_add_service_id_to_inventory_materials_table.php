<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_materials', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_materials', 'service_id')) {
                $table->foreignId('service_id')
                    ->nullable()
                    ->after('unit_id')
                    ->constrained('services')
                    ->nullOnDelete();

                $table->index(['service_id', 'activo'], 'idx_inventory_materials_service_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_materials', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_materials', 'service_id')) {
                $table->dropIndex('idx_inventory_materials_service_active');
                $table->dropConstrainedForeignId('service_id');
            }
        });
    }
};
