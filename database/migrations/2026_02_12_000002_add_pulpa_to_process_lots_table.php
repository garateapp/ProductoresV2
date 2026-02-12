<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            if (! Schema::hasColumn('process_lots', 'pulpa')) {
                $table->string('pulpa', 120)->nullable()->after('categoria_origen');
            }
        });
    }

    public function down(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            if (Schema::hasColumn('process_lots', 'pulpa')) {
                $table->dropColumn('pulpa');
            }
        });
    }
};

