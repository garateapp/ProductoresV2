<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_cooling_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_id')->nullable()->constrained('pre_cooling_loads')->nullOnDelete();
            $table->string('folio', 50)->nullable();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('accion', 50);
            $table->json('datos_antes')->nullable();
            $table->json('datos_despues')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['load_id', 'created_at']);
            $table->index('folio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_cooling_audit_logs');
    }
};
