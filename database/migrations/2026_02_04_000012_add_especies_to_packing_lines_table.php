<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packing_lines', function (Blueprint $table) {
            $table->json('especies')->nullable()->after('especie');
        });
    }

    public function down(): void
    {
        Schema::table('packing_lines', function (Blueprint $table) {
            $table->dropColumn('especies');
        });
    }
};

