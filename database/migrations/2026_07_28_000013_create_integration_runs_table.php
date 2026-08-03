<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('integration_profiles');
            $table->foreignId('profile_version_id')->constrained('integration_profile_versions');
            $table->string('estado', 30)->default('pending'); // pending, preparing, processing, partially_completed, completed, failed, cancelled
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('total_registros')->unsigned()->default(0);
            $table->integer('procesados')->unsigned()->default(0);
            $table->integer('exitosos')->unsigned()->default(0);
            $table->integer('pendientes')->unsigned()->default(0);
            $table->integer('fallidos')->unsigned()->default(0);
            $table->string('archivo_generado', 500)->nullable();
            $table->string('batch_id', 100)->nullable();
            $table->integer('duracion_segundos')->unsigned()->nullable();
            $table->json('metricas')->nullable();
            $table->json('errores')->nullable();
            $table->text('nota')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['profile_id', 'estado']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_runs');
    }
};
