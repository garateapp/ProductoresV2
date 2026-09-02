<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_manual_consumptions', function (Blueprint $table) {
            $table->unsignedBigInteger('id_g_produccion')->nullable()->after('material_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_manual_consumptions', function (Blueprint $table) {
            $table->dropColumn('id_g_produccion');
        });
    }
};
