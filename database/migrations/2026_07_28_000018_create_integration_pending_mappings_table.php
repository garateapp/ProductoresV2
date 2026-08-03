<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_pending_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('integration_clients');
            $table->foreignId('profile_id')->nullable()->constrained('integration_profiles')->nullOnDelete();
            $table->foreignId('mapping_set_id')->nullable()->constrained('integration_mapping_sets')->nullOnDelete();
            $table->foreignId('run_record_id')->nullable()->constrained('integration_run_records')->nullOnDelete();
            $table->string('campo', 100);
            $table->string('valor_interno', 500);
            $table->integer('frecuencia')->unsigned()->default(1);
            $table->string('valor_asignado', 500)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'campo', 'valor_interno']);
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_pending_mappings');
    }
};
