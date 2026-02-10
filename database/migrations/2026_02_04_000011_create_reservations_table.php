<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('n_g_recepcion', 64)->unique();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->string('estado', 32);
            $table->timestamps();

            $table->index(['process_id'], 'idx_reservations_process');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};

