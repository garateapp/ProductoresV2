<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimation_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimation_version_id')->constrained('estimation_versions');
            $table->foreignId('estimation_row_id')->nullable()->constrained('estimation_rows');
            $table->string('field_name', 80);
            $table->string('action', 24);
            $table->string('source', 24)->default('upload');
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['estimation_version_id', 'estimation_row_id'], 'idx_estimation_audits_row');
            $table->index(['changed_by', 'changed_at'], 'idx_estimation_audits_user_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimation_audits');
    }
};