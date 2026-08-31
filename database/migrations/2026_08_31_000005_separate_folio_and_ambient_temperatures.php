<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->decimal('temperatura_inicio', 5, 2)->nullable()->after('temperatura_inicial');
            $table->decimal('temperatura_inversion_interior', 5, 2)->nullable()->after('temperatura_inicio');
            $table->decimal('temperatura_inversion_exterior', 5, 2)->nullable()->after('temperatura_inversion_interior');
            $table->decimal('temperatura_final_interna', 5, 2)->nullable()->after('temperatura_inversion_exterior');
            $table->decimal('temperatura_final_externa', 5, 2)->nullable()->after('temperatura_final_interna');
        });

        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->decimal('temperatura_ambiente_inicio', 5, 2)->nullable()->after('temperatura_objetivo');
            $table->decimal('temperatura_ambiente_inversion', 5, 2)->nullable()->after('temperatura_ambiente_inicio');
            $table->decimal('temperatura_ambiente_final', 5, 2)->nullable()->after('temperatura_ambiente_inversion');
            $table->dropColumn([
                'temperatura_inicio',
                'temperatura_inversion_interior',
                'temperatura_inversion_exterior',
                'temperatura_final_interna',
                'temperatura_final_externa',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->decimal('temperatura_inicio', 5, 2)->nullable();
            $table->decimal('temperatura_inversion_interior', 5, 2)->nullable();
            $table->decimal('temperatura_inversion_exterior', 5, 2)->nullable();
            $table->decimal('temperatura_final_interna', 5, 2)->nullable();
            $table->decimal('temperatura_final_externa', 5, 2)->nullable();
            $table->dropColumn([
                'temperatura_ambiente_inicio',
                'temperatura_ambiente_inversion',
                'temperatura_ambiente_final',
            ]);
        });

        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->dropColumn([
                'temperatura_inicio',
                'temperatura_inversion_interior',
                'temperatura_inversion_exterior',
                'temperatura_final_interna',
                'temperatura_final_externa',
            ]);
        });
    }
};
