<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimation_biweekly_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('estimation_seasons');
            $table->unsignedSmallInteger('period_start_week')->nullable();
            $table->unsignedSmallInteger('period_end_week')->nullable();
            $table->string('source', 32)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->string('status', 32);
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['season_id', 'status'], 'idx_est_biweekly_season_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimation_biweekly_versions');
    }
};
