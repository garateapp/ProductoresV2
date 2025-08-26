<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('market_especie', function (Blueprint $table) {
            $table->unsignedBigInteger('market_id');
            $table->unsignedBigInteger('especie_id');
            $table->foreign('market_id')->references('id')->on('markets')->onDelete('cascade');
            $table->foreign('especie_id')->references('id')->on('especies')->onDelete('cascade');
            $table->primary(['market_id', 'especie_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_especie');
    }
};