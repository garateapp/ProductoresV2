<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            if (! Schema::hasColumn('process_lots', 'porcentaje_exportacion')) {
                $table->decimal('porcentaje_exportacion', 5, 2)->nullable()->after('sispro');
            }
            if (! Schema::hasColumn('process_lots', 'numproceso')) {
                $table->string('numproceso', 50)->nullable()->after('porcentaje_exportacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            if (Schema::hasColumn('process_lots', 'numproceso')) {
                $table->dropColumn('numproceso');
            }
            if (Schema::hasColumn('process_lots', 'porcentaje_exportacion')) {
                $table->dropColumn('porcentaje_exportacion');
            }
        });
    }
};
