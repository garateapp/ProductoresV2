<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_technical_sheet_unit_items', function (Blueprint $table) {
            $table->string('calibre', 20)->nullable()->after('replacement_material_id');
        });

        Schema::table('inventory_technical_sheet_pallet_items', function (Blueprint $table) {
            $table->string('calibre', 20)->nullable()->after('replacement_material_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_technical_sheet_unit_items', function (Blueprint $table) {
            $table->dropColumn('calibre');
        });

        Schema::table('inventory_technical_sheet_pallet_items', function (Blueprint $table) {
            $table->dropColumn('calibre');
        });
    }
};
