<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimation_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('estimation_seasons');
            $table->unsignedTinyInteger('week_number');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['season_id', 'week_number'], 'uq_estimation_weeks');
            $table->index(['season_id', 'week_number'], 'idx_estimation_weeks_season');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimation_weeks');
    }
};