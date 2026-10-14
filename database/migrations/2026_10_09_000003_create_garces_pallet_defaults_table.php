<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garces_pallet_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('code_subsidiary', 20);
            $table->string('code_season', 20);
            $table->string('subsidiary_garces', 20)->nullable();
            $table->string('season_garces', 20)->nullable();
            $table->string('tipo_tarja', 40)->nullable();
            $table->string('tipo_fruta', 40)->nullable();
            $table->string('tipo_pallet', 40)->nullable();
            $table->unsignedTinyInteger('is_export')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['code_subsidiary', 'code_season'], 'ux_garces_pallet_defaults_planta_temporada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garces_pallet_defaults');
    }
};
