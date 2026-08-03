<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('evento', 100);
            $table->string('entidad_tipo', 100);
            $table->unsignedBigInteger('entidad_id');
            $table->string('entidad_nombre', 200)->nullable();
            $table->json('valores_previos')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('motivo')->nullable();
            $table->foreignId('run_id')->nullable()->constrained('integration_runs')->nullOnDelete();
            $table->timestamps();

            $table->index(['entidad_tipo', 'entidad_id']);
            $table->index('evento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_audit_logs');
    }
};
