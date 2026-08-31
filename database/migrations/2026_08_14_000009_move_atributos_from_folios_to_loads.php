<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->json('atributos')->nullable()->after('temperatura_objetivo');
        });

        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->dropColumn('atributos');
        });
    }

    public function down(): void
    {
        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->json('atributos')->nullable();
        });

        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->dropColumn('atributos');
        });
    }
};
