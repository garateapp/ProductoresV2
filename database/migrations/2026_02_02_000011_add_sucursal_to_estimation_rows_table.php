<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimation_rows', function (Blueprint $table) {
            $table->string('sucursal', 120)->default('')->after('producer_id');
        });

        Schema::table('estimation_rows', function (Blueprint $table) {
            $table->index('estimation_version_id', 'idx_estimation_rows_version');
            $table->dropUnique('uq_estimation_rows_key');
            $table->unique(
                ['estimation_version_id', 'producer_id', 'sucursal', 'variedad_id', 'radio_mosca'],
                'uq_estimation_rows_key'
            );
        });
    }

    public function down(): void
    {
        Schema::table('estimation_rows', function (Blueprint $table) {
            $table->dropUnique('uq_estimation_rows_key');
            $table->unique(
                ['estimation_version_id', 'producer_id', 'variedad_id', 'radio_mosca'],
                'uq_estimation_rows_key'
            );
            $table->dropIndex('idx_estimation_rows_version');
            $table->dropColumn('sucursal');
        });
    }
};
