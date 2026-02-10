<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_lot_packaging_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('process_lot_id')->constrained('process_lots')->cascadeOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('from_c_embalaje', 60)->nullable();
            $table->string('from_n_embalaje', 160)->nullable();
            $table->string('to_c_embalaje', 60)->nullable();
            $table->string('to_n_embalaje', 160)->nullable();

            $table->string('reason', 500);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['process_id', 'process_lot_id'], 'idx_pack_change_process_lot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_lot_packaging_changes');
    }
};

