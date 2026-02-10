<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimation_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimation_version_id')->constrained('estimation_versions');
            $table->string('grupo', 191)->nullable();
            $table->string('tipo_productor', 80)->nullable();
            $table->foreignId('producer_id')->constrained('users');
            $table->foreignId('agronomist_id')->nullable()->constrained('users');
            $table->foreignId('status_id')->constrained('estimation_statuses');
            $table->foreignId('variedad_id')->constrained('variedads');
            $table->boolean('acopio')->default(false);
            $table->boolean('radio_mosca')->default(false);
            $table->boolean('corea_greenex')->nullable();
            $table->string('tipo_cereza', 60)->nullable();
            $table->decimal('total_kilo', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(
                ['estimation_version_id', 'producer_id', 'variedad_id', 'radio_mosca'],
                'uq_estimation_rows_key'
            );
            $table->index(['producer_id', 'variedad_id'], 'idx_estimation_rows_producer_variedad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimation_rows');
    }
};