<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add banda/posicion/altura to load_folios
        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->string('banda', 50)->after('load_id');
            $table->string('posicion', 50)->after('banda');
            $table->string('altura', 50)->after('posicion');
        });

        // 2. Migrate existing data: copy position from loads to their folios
        DB::statement('
            UPDATE pre_cooling_load_folios f
            JOIN pre_cooling_loads l ON f.load_id = l.id
            SET f.banda = l.banda, f.posicion = l.posicion, f.altura = l.altura
        ');

        // 3. Drop old unique constraints on load_folios
        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->dropUnique(['load_id', 'nivel']);
        });

        // 4. Add new unique constraint on load_folios (short name for MySQL)
        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->unique(['load_id', 'banda', 'posicion', 'altura', 'nivel'], 'pf_celda_unique');
        });

        // 5. Drop position columns and unique constraint from loads
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->dropUnique(['tunel_id', 'banda', 'posicion', 'altura']);
            $table->dropColumn(['banda', 'posicion', 'altura']);
        });

        // 6. Add unique constraint: only one active state per tunnel (short name)
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->unique(['tunel_id', 'estado'], 'pl_tunel_estado_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->dropIndex('pl_tunel_estado_unique');
            $table->string('banda', 50);
            $table->string('posicion', 50);
            $table->string('altura', 50);
            $table->unique(['tunel_id', 'banda', 'posicion', 'altura']);
        });

        Schema::table('pre_cooling_load_folios', function (Blueprint $table) {
            $table->dropIndex('pf_celda_unique');
            $table->unique(['load_id', 'nivel']);
            $table->dropColumn(['banda', 'posicion', 'altura']);
        });
    }
};
