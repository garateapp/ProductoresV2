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
        Schema::create('commercial_discard_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_discard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parametro_id')->constrained('parametros');
            $table->foreignId('valor_id')->constrained('valors');
            $table->integer('comercial')->default(0);
            $table->integer('desecho')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercial_discard_details');
    }
};
