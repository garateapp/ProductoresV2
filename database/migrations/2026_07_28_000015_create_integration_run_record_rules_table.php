<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_run_record_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_record_id')->constrained('integration_run_records')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('integration_rules')->nullOnDelete();
            $table->string('rule_name', 200);
            $table->string('rule_type', 50);
            $table->string('estado', 20)->default('success'); // success, failed, skipped, warning
            $table->integer('duracion_ms')->unsigned()->nullable();
            $table->json('input_values')->nullable();
            $table->json('output_values')->nullable();
            $table->json('error')->nullable();

            $table->index('run_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_run_record_rules');
    }
};
