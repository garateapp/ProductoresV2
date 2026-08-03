<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_run_record_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_record_id')->constrained('integration_run_records')->cascadeOnDelete();
            $table->foreignId('mapping_set_version_id')->nullable()->constrained('integration_mapping_set_versions')->nullOnDelete();
            $table->string('mapping_set_name', 200);
            $table->json('input_keys')->nullable();
            $table->json('output_values')->nullable();
            $table->string('fallback_usado', 30)->nullable();

            $table->index('run_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_run_record_mappings');
    }
};
