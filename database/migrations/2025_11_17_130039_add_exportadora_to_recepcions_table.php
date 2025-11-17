<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcions', 'exportadora')) {
                $table->string('exportadora')->nullable()->after('Codigo_Sag_emisor');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            if (Schema::hasColumn('recepcions', 'exportadora')) {
                $table->dropColumn('exportadora');
            }
        });
    }
};
