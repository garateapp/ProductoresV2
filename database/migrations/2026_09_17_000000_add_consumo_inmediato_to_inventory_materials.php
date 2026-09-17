<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_materials', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_materials', 'consumo_inmediato')) {
                $table->boolean('consumo_inmediato')->default(false)->after('tipo_material');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_materials', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_materials', 'consumo_inmediato')) {
                $table->dropColumn('consumo_inmediato');
            }
        });
    }
};