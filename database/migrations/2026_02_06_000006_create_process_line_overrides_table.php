<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_line_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->foreignId('packing_line_id')->constrained('packing_lines');
            $table->decimal('extra_horas', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(['process_id', 'packing_line_id'], 'uq_process_line_overrides_process_line');
            $table->index(['process_id'], 'idx_process_line_overrides_process');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_line_overrides');
    }
};

