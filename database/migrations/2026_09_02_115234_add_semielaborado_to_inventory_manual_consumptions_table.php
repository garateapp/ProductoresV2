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
            $table->foreignId('semielaborado_material_id')->nullable()->after('id_g_produccion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_manual_consumptions', function (Blueprint $table) {
            $table->dropColumn('semielaborado_material_id');
        });
    }
};
