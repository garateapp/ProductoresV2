<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            $table->unsignedBigInteger('id_productor')->nullable()->after('n_variedad');
            $table->string('c_productor', 60)->nullable()->after('id_productor');
            $table->string('csg_productor', 60)->nullable()->after('c_productor');
            $table->string('n_productor', 180)->nullable()->after('csg_productor');
        });
    }

    public function down(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            $table->dropColumn(['id_productor', 'c_productor', 'csg_productor', 'n_productor']);
        });
    }
};

