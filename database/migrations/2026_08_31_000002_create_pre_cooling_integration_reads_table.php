<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_cooling_integration_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_cooling_load_id')
                ->constrained('pre_cooling_loads')
                ->cascadeOnDelete();
            $table->json('folios_found')->nullable();
            $table->json('folios_missing')->nullable();
            $table->boolean('is_partial_success')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index('pre_cooling_load_id', 'idx_pc_reads_load');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_cooling_integration_reads');
    }
};
