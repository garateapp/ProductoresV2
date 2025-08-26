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
        Schema::create('market_variedad', function (Blueprint $table) {
            $table->unsignedBigInteger('market_id');
            $table->unsignedBigInteger('variedad_id');

            $table->foreign('market_id')->references('id')->on('markets')->onDelete('cascade');
            $table->foreign('variedad_id')->references('id')->on('variedads')->onDelete('cascade');

            $table->primary(['market_id', 'variedad_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_variedad');
    }
};