<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('estimation_seasons');
            $table->string('type', 32);
            $table->unsignedTinyInteger('period_start_week')->nullable();
            $table->unsignedTinyInteger('period_end_week')->nullable();
            $table->string('source', 32)->default('upload');
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->string('status', 16)->default('active');
            $table->string('file_name', 191)->nullable();
            $table->string('file_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['season_id', 'type', 'status'], 'idx_estimation_versions_season_type_status');
            $table->index(['season_id', 'period_start_week', 'period_end_week'], 'idx_estimation_versions_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimation_versions');
    }
};