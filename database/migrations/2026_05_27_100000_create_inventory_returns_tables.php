<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_returns', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('origin_location_id')->constrained('inventory_locations');
            $table->foreignId('destination_location_id')->constrained('inventory_locations');
            $table->string('estado', 50)->default('pendiente');
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->timestamp('fecha_requerida')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('inventory_returns')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->foreignId('position_id')->nullable()->constrained('inventory_stock_positions')->nullOnDelete();
            $table->decimal('cantidad_devuelta', 18, 4);
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('return_id')->nullable()->constrained('inventory_returns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['return_id']);
            $table->dropColumn('return_id');
        });
        Schema::dropIfExists('inventory_return_items');
        Schema::dropIfExists('inventory_returns');
    }
};
