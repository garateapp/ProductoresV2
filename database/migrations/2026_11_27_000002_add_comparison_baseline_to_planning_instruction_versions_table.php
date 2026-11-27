<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_instruction_versions', function (Blueprint $table) {
            $table->boolean('is_comparison_baseline')->default(false)->after('sheets');
        });
    }

    public function down(): void
    {
        Schema::table('planning_instruction_versions', function (Blueprint $table) {
            $table->dropColumn('is_comparison_baseline');
        });
    }
};
