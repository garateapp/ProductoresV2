<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_instruction_versions', function (Blueprint $table) {
            $table->id();

            $table->date('fecha');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->foreignId('packing_line_id')->constrained('packing_lines');

            $table->unsignedInteger('version');
            $table->longText('html');

            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->dateTime('changed_at');

            $table->timestamps();

            $table->unique(['fecha', 'shift_id', 'packing_line_id', 'version'], 'uq_planning_instr_ver');
            $table->index(['fecha', 'shift_id', 'packing_line_id'], 'idx_planning_instr_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_instruction_versions');
    }
};

