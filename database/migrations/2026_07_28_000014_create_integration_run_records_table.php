<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_run_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('integration_runs')->cascadeOnDelete();
            $table->foreignId('reprocess_of_id')->nullable()->constrained('integration_run_records')->nullOnDelete();
            $table->string('source_identifier', 200)->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->string('estado', 30)->default('pending'); // pending, processing, success, pending_mapping, failed, skipped, duplicate, reprocessed
            $table->json('input_original')->nullable();
            $table->json('input_normalizado')->nullable();
            $table->json('output_generado')->nullable();
            $table->json('errores')->nullable();
            $table->json('advertencias')->nullable();
            $table->integer('intentos')->unsigned()->default(1);
            $table->integer('duracion_ms')->unsigned()->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'idempotency_key'], 'ux_run_idempotency');
            $table->index(['run_id', 'estado']);
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_run_records');
    }
};
