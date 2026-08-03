<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_movements', 'ledger_hash')) {
                $table->char('ledger_hash', 64)->nullable()->after('receipt_hash');
            }
            if (! Schema::hasColumn('inventory_movements', 'ledger_sequence_from')) {
                $table->unsignedBigInteger('ledger_sequence_from')->nullable()->after('ledger_hash');
            }
            if (! Schema::hasColumn('inventory_movements', 'ledger_sequence_to')) {
                $table->unsignedBigInteger('ledger_sequence_to')->nullable()->after('ledger_sequence_from');
            }
            if (! Schema::hasColumn('inventory_movements', 'scan_session_uuid')) {
                $table->uuid('scan_session_uuid')->nullable()->after('ledger_sequence_to');
            }
            if (! Schema::hasColumn('inventory_movements', 'waste_reason_id')) {
                $table->foreignId('waste_reason_id')->nullable()->after('scan_session_uuid')->constrained('inventory_waste_reasons');
            }
            if (! Schema::hasColumn('inventory_movements', 'reversal_of_movement_id')) {
                $table->foreignId('reversal_of_movement_id')->nullable()->after('waste_reason_id')->constrained('inventory_movements');
            }
            if (! Schema::hasColumn('inventory_movements', 'requires_photo_evidence')) {
                $table->boolean('requires_photo_evidence')->default(false)->after('reversal_of_movement_id');
            }
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index('scan_session_uuid', 'idx_inventory_movements_scan_session');
            $table->index('reversal_of_movement_id', 'idx_inventory_movements_reversal_of');
            $table->index(['waste_reason_id', 'estado'], 'idx_inventory_movements_waste_reason_status');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('idx_inventory_movements_scan_session');
            $table->dropIndex('idx_inventory_movements_reversal_of');
            $table->dropIndex('idx_inventory_movements_waste_reason_status');
            $table->dropConstrainedForeignId('waste_reason_id');
            $table->dropConstrainedForeignId('reversal_of_movement_id');
            $table->dropColumn([
                'ledger_hash',
                'ledger_sequence_from',
                'ledger_sequence_to',
                'scan_session_uuid',
                'requires_photo_evidence',
            ]);
        });
    }
};
