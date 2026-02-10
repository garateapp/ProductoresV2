<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_capacities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_line_id')->constrained('packing_lines');
            $table->string('especie', 80);
            $table->decimal('bins_por_hora', 10, 2);
            $table->foreignId('shift_id')->nullable()->constrained('shifts');
            $table->date('vigencia_desde');
            $table->date('vigencia_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['packing_line_id', 'especie', 'activo'], 'idx_line_cap_line_especie_activo');
            $table->index(['shift_id', 'vigencia_desde', 'vigencia_hasta'], 'idx_line_cap_shift_vigencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_capacities');
    }
};

