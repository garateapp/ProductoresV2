<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_mapping_item_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapping_item_id')->constrained('integration_mapping_items')->cascadeOnDelete();
            $table->string('clave', 100);
            $table->string('valor_entrada', 500);

            $table->index(['mapping_item_id', 'clave']);
            $table->index(['clave', 'valor_entrada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_mapping_item_inputs');
    }
};
