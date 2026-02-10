<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimation_biweekly_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimation_biweekly_version_id')->constrained('estimation_biweekly_versions');
            $table->foreignId('producer_id')->constrained('users');
            $table->foreignId('agronomist_id')->nullable()->constrained('users');
            $table->foreignId('variedad_id')->constrained('variedads');
            $table->string('planta', 120)->nullable();
            $table->string('sucursal', 120)->default('');
            $table->string('csg', 64)->nullable();
            $table->string('especie', 80)->nullable();
            $table->string('tipo', 80)->nullable();
            $table->boolean('acopio')->default(false);
            $table->boolean('mexico')->nullable();
            $table->date('dia')->nullable();
            $table->unsignedSmallInteger('semana')->nullable();
            $table->decimal('total_kilo', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(
                ['estimation_biweekly_version_id', 'producer_id', 'sucursal', 'variedad_id', 'dia', 'total_kilo'],
                'uq_estimation_biweekly_rows_key'
            );
            $table->index(['producer_id', 'variedad_id'], 'idx_est_biweekly_producer_variedad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimation_biweekly_rows');
    }
};
