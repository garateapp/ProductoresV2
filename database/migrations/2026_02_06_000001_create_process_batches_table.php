<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_batches', function (Blueprint $table) {
            $table->id();
            $table->string('especie', 80);
            $table->date('week_start');
            $table->date('week_end');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->string('estado', 32);
            $table->foreignId('creado_por')->nullable()->constrained('users');
            $table->json('included_packing_line_ids')->nullable();
            $table->timestamps();

            $table->index(['especie', 'week_start'], 'idx_process_batches_especie_start');
            $table->index(['estado'], 'idx_process_batches_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_batches');
    }
};

