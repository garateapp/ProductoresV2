<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('inventory_technical_sheet_unit_items', 'replacement_material_id')) {
            Schema::table('inventory_technical_sheet_unit_items', function (Blueprint $table) {
                $table->unsignedBigInteger('replacement_material_id')->nullable();
            });
        }
        
        Schema::table('inventory_technical_sheet_unit_items', function (Blueprint $table) {
            $table->foreign('replacement_material_id', 'ts_unit_replacement_fk')
                  ->references('id')->on('inventory_materials');
        });

        Schema::table('inventory_technical_sheet_pallet_items', function (Blueprint $table) {
            $table->foreignId('replacement_material_id')->nullable()->constrained('inventory_materials', 'id', 'ts_pallet_replacement_fk');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_technical_sheet_unit_items', function (Blueprint $table) {
            $table->dropForeign('ts_unit_replacement_fk');
            $table->dropColumn('replacement_material_id');
        });

        Schema::table('inventory_technical_sheet_pallet_items', function (Blueprint $table) {
            $table->dropForeign('ts_pallet_replacement_fk');
            $table->dropColumn('replacement_material_id');
        });
    }
};
