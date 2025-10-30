<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procesos', function (Blueprint $table) {
            $table->string('LPP_recepcion', 255)->nullable()->after('c_productor');
        });
    }

    public function down(): void
    {
        Schema::table('procesos', function (Blueprint $table) {
            $table->dropColumn('LPP_recepcion');
        });
    }
};
