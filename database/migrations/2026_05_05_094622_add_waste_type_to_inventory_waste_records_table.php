<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->boolean('es_bodega_central')->default(false);
        });

        Schema::table('inventory_waste_types', function (Blueprint $table) {
            $table->boolean('permite_devolucion')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropColumn('es_bodega_central');
        });

        Schema::table('inventory_waste_types', function (Blueprint $table) {
            $table->dropColumn('permite_devolucion');
        });
    }
};
