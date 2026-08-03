<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('material_request_id')->nullable()->after('destination_location_id')->constrained('inventory_material_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['material_request_id']);
            $table->dropColumn('material_request_id');
        });
    }
};
