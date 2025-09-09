<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('weekly_harvest_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            // Agrónomo responsable (desde relación campo_staff). Nullable.
            $table->foreignId('agronomist_id')->nullable()->constrained('users');

            $table->foreignId('especie_id')->constrained('especies');
            $table->foreignId('variedad_id')->nullable()->constrained('variedads');

            $table->string('season_code', 32);
            $table->unsignedSmallInteger('iso_year');
            $table->unsignedTinyInteger('iso_week');
            $table->date('week_start_date')->nullable();
            $table->date('week_end_date')->nullable();

            $table->string('predio', 191)->nullable();
            $table->string('block', 191)->nullable();

            $table->decimal('estimated_kilos', 12, 2);
            $table->decimal('estimated_bins', 12, 2)->nullable();
            $table->unsignedInteger('estimated_boxes')->nullable();
            $table->unsignedTinyInteger('confidence_pct')->nullable();

            $table->string('status', 24)->default('draft');
            $table->string('source', 32)->default('manual');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();

            // Evitar duplicados por semana y ámbito.
            $table->unique([
                'user_id', 'especie_id', 'variedad_id',
                'season_code', 'iso_year', 'iso_week',
                'predio', 'block'
            ], 'uq_weekly_estimates_scope');

            $table->index(['user_id','season_code','iso_year','iso_week'], 'idx_user_season_week');
            $table->index(['especie_id','variedad_id'], 'idx_especie_variedad');
            $table->index('season_code', 'idx_season_code');
            $table->index(['agronomist_id'], 'idx_agronomist');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_harvest_estimates');
    }
};

