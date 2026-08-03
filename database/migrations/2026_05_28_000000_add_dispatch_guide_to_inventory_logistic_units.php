<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_logistic_units', function (Blueprint $table) {
            $table->string('dispatch_guide', 100)->nullable()->after('last_moved_at');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_logistic_units', function (Blueprint $table) {
            $table->dropColumn('dispatch_guide');
        });
    }
};
