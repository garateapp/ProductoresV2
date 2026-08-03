<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_rule_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('integration_rules')->cascadeOnDelete();
            $table->foreignId('input_field_id')->nullable()->constrained('integration_input_fields')->nullOnDelete();
            $table->string('clave_origen', 100);
            $table->string('alias', 100)->nullable();

            $table->unique(['rule_id', 'clave_origen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_rule_inputs');
    }
};
