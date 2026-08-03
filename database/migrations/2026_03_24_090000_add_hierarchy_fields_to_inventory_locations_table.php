<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_locations', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('inventory_locations');
            }
            if (! Schema::hasColumn('inventory_locations', 'scan_code')) {
                $table->string('scan_code', 100)->nullable()->unique()->after('codigo');
            }
            if (! Schema::hasColumn('inventory_locations', 'depth')) {
                $table->unsignedTinyInteger('depth')->default(0)->after('tipo');
            }
            if (! Schema::hasColumn('inventory_locations', 'path_code')) {
                $table->string('path_code', 255)->nullable()->after('depth');
            }
            if (! Schema::hasColumn('inventory_locations', 'es_ubicacion_operable')) {
                $table->boolean('es_ubicacion_operable')->default(true)->after('path_code');
            }
            if (! Schema::hasColumn('inventory_locations', 'requiere_confirmacion_scan')) {
                $table->boolean('requiere_confirmacion_scan')->default(false)->after('es_ubicacion_operable');
            }
            if (! Schema::hasColumn('inventory_locations', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('requiere_confirmacion_scan');
            }
        });

        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->index(['tipo', 'activo', 'es_ubicacion_operable'], 'idx_inventory_locations_type_active_operable');
            $table->index('path_code', 'idx_inventory_locations_path_code');
        });

        DB::table('inventory_locations')
            ->whereNull('scan_code')
            ->update(['scan_code' => DB::raw('codigo')]);

        DB::table('inventory_locations')
            ->whereNull('path_code')
            ->update(['path_code' => DB::raw('codigo')]);
    }

    public function down(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropIndex('idx_inventory_locations_type_active_operable');
            $table->dropIndex('idx_inventory_locations_path_code');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn([
                'scan_code',
                'depth',
                'path_code',
                'es_ubicacion_operable',
                'requiere_confirmacion_scan',
                'sort_order',
            ]);
        });
    }
};
