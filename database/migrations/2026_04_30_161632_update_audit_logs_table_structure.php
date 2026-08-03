<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event'); // created, updated, deleted, status_changed
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent']);
        });
    }
};
