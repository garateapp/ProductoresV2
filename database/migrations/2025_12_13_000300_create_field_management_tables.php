<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('crews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignUuid('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('workers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('national_id')->nullable()->index();
            $table->string('full_name');
            $table->string('role')->nullable();
            $table->string('status')->default('active');
            $table->foreignUuid('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->foreignUuid('crew_id')->nullable()->constrained('crews')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('qr_uid')->unique();
            $table->string('status')->default('available');
            $table->timestamps();
        });

        Schema::create('worker_credential_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->foreignUuid('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unassigned_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['worker_id', 'credential_id', 'assigned_at'], 'uq_worker_credential_assigned_at');
        });

        Schema::create('fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('producer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('field_id')->constrained('fields')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('fruit_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('species');
            $table->string('variety')->nullable();
            $table->unsignedInteger('tottes_per_bin')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fruit_configs');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('fields');
        Schema::dropIfExists('worker_credential_links');
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('workers');
        Schema::dropIfExists('crews');
        Schema::dropIfExists('contractors');
    }
};
