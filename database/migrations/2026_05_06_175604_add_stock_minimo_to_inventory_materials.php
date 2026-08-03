<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_materials', function (Blueprint $table) {
            $table->decimal('stock_minimo', 16, 4)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_materials', function (Blueprint $table) {
            $table->dropColumn('stock_minimo');
        });
    }
};
