<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimation_biweekly_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estimation_biweekly_version_id');
            $table->unsignedBigInteger('estimation_biweekly_row_id');
            $table->string('field_name', 80);
            $table->string('action', 40);
            $table->string('source', 40)->nullable();
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();

            $table->foreign('estimation_biweekly_version_id', 'fk_est_biweekly_audit_ver')
                ->references('id')
                ->on('estimation_biweekly_versions');
            $table->foreign('estimation_biweekly_row_id', 'fk_est_biweekly_audit_row')
                ->references('id')
                ->on('estimation_biweekly_rows');
            $table->foreign('changed_by', 'fk_est_biweekly_audit_user')
                ->references('id')
                ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimation_biweekly_audits');
    }
};
