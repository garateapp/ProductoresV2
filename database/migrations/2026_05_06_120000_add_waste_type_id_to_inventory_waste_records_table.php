<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_waste_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_waste_records', 'waste_type_id')) {
                $table->foreignId('waste_type_id')
                    ->nullable()
                    ->after('waste_reason_id')
                    ->constrained('inventory_waste_types');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_waste_records', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_waste_records', 'waste_type_id')) {
                $table->dropConstrainedForeignId('waste_type_id');
            }
        });
    }
};
