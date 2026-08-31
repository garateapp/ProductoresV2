<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->dropIndex('pl_tunel_estado_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pre_cooling_loads', function (Blueprint $table) {
            $table->unique(['tunel_id', 'estado'], 'pl_tunel_estado_unique');
        });
    }
};
