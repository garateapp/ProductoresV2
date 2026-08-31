<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_consumption_origins', function (Blueprint $table) {
            $table->id();
            $table->string('linea', 100);
            $table->string('turno', 50)->default('');
            $table->foreignId('location_id')->constrained('inventory_locations');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['linea', 'turno'], 'ux_consumption_origins_linea_turno');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_consumption_origins');
    }
};