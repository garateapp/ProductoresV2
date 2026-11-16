<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            if (! Schema::hasColumn('process_lots', 'sispro')) {
                $table->string('sispro', 100)->nullable()->after('huerto');
                $table->index('sispro', 'idx_process_lots_sispro');
            }
        });
    }

    public function down(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            if (Schema::hasColumn('process_lots', 'sispro')) {
                $table->dropIndex('idx_process_lots_sispro');
                $table->dropColumn('sispro');
            }
        });
    }
};
