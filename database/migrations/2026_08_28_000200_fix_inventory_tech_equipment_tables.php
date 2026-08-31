<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_tech_equipment_delivery_acts', function (Blueprint $table) {
            $table->longText('signature_data_url')->nullable()->change();
            $table->longText('return_signature_data_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_tech_equipment_delivery_acts', function (Blueprint $table) {
            $table->string('signature_data_url')->nullable()->change();
            $table->string('return_signature_data_url')->nullable()->change();
        });
    }
};
