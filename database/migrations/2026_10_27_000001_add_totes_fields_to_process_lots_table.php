<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            if (! Schema::hasColumn('process_lots', 'cantidad_totes')) {
                $table->unsignedInteger('cantidad_totes')->nullable()->after('cantidad_bins');
            }
            if (! Schema::hasColumn('process_lots', 'peso_promedio_envase')) {
                $table->decimal('peso_promedio_envase', 12, 3)->nullable()->after('peso_neto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            if (Schema::hasColumn('process_lots', 'peso_promedio_envase')) {
                $table->dropColumn('peso_promedio_envase');
            }
            if (Schema::hasColumn('process_lots', 'cantidad_totes')) {
                $table->dropColumn('cantidad_totes');
            }
        });
    }
};
