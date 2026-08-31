<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_auto_consumption_folios', function (Blueprint $table) {
            $table->boolean('es_temporal')->default(false)->after('folio');
            $table->index(['es_temporal'], 'idx_auto_consumption_es_temporal');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_auto_consumption_folios', function (Blueprint $table) {
            $table->dropIndex('idx_auto_consumption_es_temporal');
            $table->dropColumn('es_temporal');
        });
    }
};