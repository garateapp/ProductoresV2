<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_line_orders', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->foreignId('packing_line_id')->constrained('packing_lines');
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->unsignedInteger('orden');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(
                ['fecha', 'shift_id', 'packing_line_id', 'process_id'],
                'ux_process_line_orders_group_process'
            );
            $table->index(
                ['fecha', 'shift_id', 'packing_line_id', 'orden'],
                'idx_process_line_orders_group_order'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_line_orders');
    }
};

