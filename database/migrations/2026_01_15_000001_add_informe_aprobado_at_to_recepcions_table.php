<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            $table->dateTime('informe_aprobado_at')->nullable()->after('informe');
        });
    }

    public function down(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            $table->dropColumn('informe_aprobado_at');
        });
    }
};
