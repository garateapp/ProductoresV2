<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_control_photos', function (Blueprint $table) {
            if (! Schema::hasColumn('quality_control_photos', 'observations')) {
                $table->string('observations')->nullable()->after('path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quality_control_photos', function (Blueprint $table) {
            if (Schema::hasColumn('quality_control_photos', 'observations')) {
                $table->dropColumn('observations');
            }
        });
    }
};
