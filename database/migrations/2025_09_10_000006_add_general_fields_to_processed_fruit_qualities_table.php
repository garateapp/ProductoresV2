<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processed_fruit_qualities', function (Blueprint $table) {
            if (! Schema::hasColumn('processed_fruit_qualities', 'numero_embaladora_mano')) {
                $table->string('numero_embaladora_mano')->nullable()->after('numero_de_caja');
            }
            if (! Schema::hasColumn('processed_fruit_qualities', 'peso_exacto_caja')) {
                $table->decimal('peso_exacto_caja', 8, 2)->nullable()->after('numero_embaladora_mano');
            }
            if (! Schema::hasColumn('processed_fruit_qualities', 'codigo_embalaje')) {
                $table->string('codigo_embalaje')->nullable()->after('peso_exacto_caja');
            }
            if (! Schema::hasColumn('processed_fruit_qualities', 'categoria')) {
                $table->string('categoria')->nullable()->after('codigo_embalaje');
            }
            if (! Schema::hasColumn('processed_fruit_qualities', 'destino')) {
                $table->string('destino')->nullable()->after('categoria');
            }
            if (! Schema::hasColumn('processed_fruit_qualities', 'calibre')) {
                $table->string('calibre')->nullable()->after('destino');
            }
            if (! Schema::hasColumn('processed_fruit_qualities', 'color_cubrimiento')) {
                $table->string('color_cubrimiento')->nullable()->after('calibre');
            }
            if (! Schema::hasColumn('processed_fruit_qualities', 'color_fondo')) {
                $table->string('color_fondo')->nullable()->after('color_cubrimiento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('processed_fruit_qualities', function (Blueprint $table) {
            foreach ([
                'color_fondo', 'color_cubrimiento', 'calibre', 'destino', 'categoria', 'codigo_embalaje', 'peso_exacto_caja', 'numero_embaladora_mano',
            ] as $col) {
                if (Schema::hasColumn('processed_fruit_qualities', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
