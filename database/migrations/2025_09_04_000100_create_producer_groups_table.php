<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('producer_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_group_id')->constrained('producer_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['producer_group_id', 'user_id'], 'uq_group_user');
            $table->index('user_id', 'idx_group_user_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producer_group_user');
        Schema::dropIfExists('producer_groups');
    }
};
