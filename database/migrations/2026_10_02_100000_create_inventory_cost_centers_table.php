<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('inventory_person_deliveries', function (Blueprint $table) {
            $table->foreignId('cost_center_id')
                ->nullable()
                ->after('origin_location_id')
                ->constrained('inventory_cost_centers')
                ->nullOnDelete();

            $table->index('cost_center_id', 'idx_inventory_person_delivery_cost_center');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_person_deliveries', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });

        Schema::dropIfExists('inventory_cost_centers');
    }
};
