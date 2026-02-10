<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_instruction_versions', function (Blueprint $table) {
            $table->json('overrides')->nullable()->after('html');
        });
    }

    public function down(): void
    {
        Schema::table('planning_instruction_versions', function (Blueprint $table) {
            $table->dropColumn('overrides');
        });
    }
};

