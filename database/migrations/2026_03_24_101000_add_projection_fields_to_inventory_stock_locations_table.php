<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_stock_locations', 'last_ledger_event_id')) {
                $table->unsignedBigInteger('last_ledger_event_id')->nullable()->after('stock_actual');
            }
            if (! Schema::hasColumn('inventory_stock_locations', 'last_rebuilt_at')) {
                $table->dateTime('last_rebuilt_at')->nullable()->after('last_ledger_event_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_locations', function (Blueprint $table) {
            $table->dropColumn(['last_ledger_event_id', 'last_rebuilt_at']);
        });
    }
};
