<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('processed_fruit_qualities', function (Blueprint $table) {
            $table->string('tolerance_label', 10)->nullable()->after('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('processed_fruit_qualities', function (Blueprint $table) {
            $table->dropColumn('tolerance_label');
        });
    }
};

