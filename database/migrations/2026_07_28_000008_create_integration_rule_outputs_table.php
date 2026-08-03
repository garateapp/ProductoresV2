<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_rule_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('integration_rules')->cascadeOnDelete();
            $table->foreignId('output_field_id')->nullable()->constrained('integration_output_fields')->nullOnDelete();
            $table->string('clave_destino', 100);

            $table->unique(['rule_id', 'clave_destino']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_rule_outputs');
    }
};
