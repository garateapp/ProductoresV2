<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_scan_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('scan_session_uuid');
            $table->foreignId('movement_id')->nullable()->constrained('inventory_movements');
            $table->string('step', 30);
            $table->string('raw_code', 255);
            $table->string('code_type', 30);
            $table->string('resolved_entity_type', 30)->nullable();
            $table->unsignedBigInteger('resolved_entity_id')->nullable();
            $table->boolean('success')->default(false);
            $table->string('message', 255)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->string('device_code', 100)->nullable();
            $table->dateTime('scanned_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('scan_session_uuid', 'idx_inventory_scan_events_session');
            $table->index(['resolved_entity_type', 'resolved_entity_id'], 'idx_inventory_scan_events_entity');
            $table->index('scanned_at', 'idx_inventory_scan_events_scanned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_scan_events');
    }
};
