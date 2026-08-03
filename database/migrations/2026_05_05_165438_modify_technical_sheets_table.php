<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_technical_sheets', function (Blueprint $table) {
            $table->foreignId('packaging_id')->nullable()->change();
            $table->foreignId('material_id')->nullable()->constrained('inventory_materials');
            $table->boolean('es_semielaborado')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_technical_sheets', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
            $table->dropColumn(['material_id', 'es_semielaborado']);
        });
    }
};
