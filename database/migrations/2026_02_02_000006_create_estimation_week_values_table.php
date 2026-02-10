<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimation_week_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimation_row_id')->constrained('estimation_rows');
            $table->unsignedTinyInteger('week_number');
            $table->decimal('kilos', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['estimation_row_id', 'week_number'], 'uq_estimation_week_values');
            $table->index(['week_number'], 'idx_estimation_week_values_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimation_week_values');
    }
};