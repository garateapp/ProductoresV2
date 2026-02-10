<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->foreignId('process_batch_id')->nullable()->after('id')->constrained('process_batches');
            $table->index(['process_batch_id', 'fecha'], 'idx_processes_batch_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->dropIndex('idx_processes_batch_fecha');
            $table->dropConstrainedForeignId('process_batch_id');
        });
    }
};

