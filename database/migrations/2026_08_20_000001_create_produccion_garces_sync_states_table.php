<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produccion_garces_sync_states', function (Blueprint $table) {
            $table->id();
            $table->dateTime('last_fecha_proceso')->nullable();
            $table->string('last_numero_proceso')->nullable();
            $table->unsignedInteger('records_sent')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->text('last_error')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produccion_garces_sync_states');
    }
};
