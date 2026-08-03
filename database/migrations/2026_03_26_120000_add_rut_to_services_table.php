<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'rut')) {
                $table->string('rut', 60)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'rut')) {
                $table->dropColumn('rut');
            }
        });
    }
};
