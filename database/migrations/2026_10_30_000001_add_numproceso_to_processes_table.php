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
            if (! Schema::hasColumn('processes', 'numproceso')) {
                $table->string('numproceso', 50)->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('processes')) {
            return;
        }

        Schema::table('processes', function (Blueprint $table) {
            if (Schema::hasColumn('processes', 'numproceso')) {
                $table->dropColumn('numproceso');
            }
        });
    }
};
