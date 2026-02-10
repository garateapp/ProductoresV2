<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->foreignId('packing_line_id')->constrained('packing_lines');
            $table->string('n_g_recepcion', 64);
            $table->unsignedTinyInteger('split_index')->default(1);

            $table->string('setup_nota_calidad', 40)->nullable();
            $table->string('setup_calibre', 80)->nullable();
            $table->string('setup_color', 80)->nullable();
            $table->string('setup_hash', 40)->nullable();

            $table->decimal('brix', 10, 2)->nullable();

            $table->foreignId('variedad_id')->nullable()->constrained('variedads');
            $table->string('n_variedad', 120)->nullable();

            $table->string('c_embalaje', 60)->nullable();
            $table->string('n_embalaje', 160)->nullable();
            $table->unsignedInteger('cp2_cajas_por_pallet')->nullable();

            $table->unsignedInteger('cantidad_bins');
            $table->decimal('peso_neto', 12, 3)->nullable();

            $table->unsignedInteger('orden');
            $table->dateTime('inicio_estimado')->nullable();
            $table->dateTime('fin_estimado')->nullable();
            $table->string('estado', 32);
            $table->timestamps();

            $table->unique(['process_id', 'n_g_recepcion', 'split_index'], 'uq_process_lots_process_recepcion_split');
            $table->index(['process_id', 'packing_line_id', 'orden'], 'idx_process_lots_line_order');
            $table->index(['setup_hash'], 'idx_process_lots_setup_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_lots');
    }
};
