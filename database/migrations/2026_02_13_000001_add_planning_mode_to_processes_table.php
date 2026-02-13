<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('processes')) {
            return;
        }

        Schema::table('processes', function (Blueprint $table) {
            if (! Schema::hasColumn('processes', 'planning_mode')) {
                $table->string('planning_mode', 24)->default('normal')->after('especie');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('processes')) {
            return;
        }

        Schema::table('processes', function (Blueprint $table) {
            if (Schema::hasColumn('processes', 'planning_mode')) {
                $table->dropColumn('planning_mode');
            }
        });
    }
};

