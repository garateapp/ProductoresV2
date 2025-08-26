<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('processed_fruit_qualities', function (Blueprint $table) {
            $table->string('numero_de_caja')->nullable()->after('proceso_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processed_fruit_qualities', function (Blueprint $table) {
            $table->dropColumn('numero_de_caja');
        });
    }
};
