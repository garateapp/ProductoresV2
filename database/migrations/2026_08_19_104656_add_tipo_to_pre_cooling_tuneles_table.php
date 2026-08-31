<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cooling_tuneles', function (Blueprint $table) {
            $table->string('tipo', 30)->after('nombre')->default('californiano');
        });
    }

    public function down(): void
    {
        Schema::table('pre_cooling_tuneles', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
