<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proceso_cuadratura_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_cuadratura_id')->constrained('proceso_cuadraturas')->cascadeOnDelete();
            $table->foreignId('proceso_id')->constrained('procesos')->cascadeOnDelete();
            $table->string('accion', 40);
            $table->text('detalle')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_nombre')->nullable();
            $table->string('actor_email')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['proceso_id', 'created_at']);
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proceso_cuadratura_eventos');
    }
};

