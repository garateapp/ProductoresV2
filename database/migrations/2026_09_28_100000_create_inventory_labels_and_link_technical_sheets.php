<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_labels', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['service_id', 'activo'], 'idx_inventory_labels_service_activo');
        });

        Schema::table('inventory_technical_sheets', function (Blueprint $table) {
            $table->foreignId('etiqueta_id')
                ->nullable()
                ->after('material_id')
                ->constrained('inventory_labels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_technical_sheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('etiqueta_id');
        });

        Schema::dropIfExists('inventory_labels');
    }
};
