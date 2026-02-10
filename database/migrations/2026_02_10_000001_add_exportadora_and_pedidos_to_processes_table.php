<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->string('exportadora', 120)->nullable()->after('especie');
            $table->text('pedidos')->nullable()->after('included_packing_line_ids');
        });
    }

    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->dropColumn(['exportadora', 'pedidos']);
        });
    }
};

