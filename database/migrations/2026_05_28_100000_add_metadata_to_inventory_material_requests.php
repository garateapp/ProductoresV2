<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_material_requests', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('fecha_requerida');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_material_requests', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
