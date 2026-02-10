<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_line_monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_line_id')->constrained('packing_lines')->cascadeOnDelete();
            $table->date('fecha');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();

            // Vinculación opcional a un proceso real (SQL Server PKG_G_Produccion)
            $table->unsignedBigInteger('sqlsrv_production_id')->nullable()->comment('PKG_G_Produccion.id en SQL Server');
            $table->string('sqlsrv_production_number', 60)->nullable()->comment('PKG_G_Produccion.numero_i');

            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->unique(['packing_line_id', 'fecha', 'shift_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_line_monitors');
    }
};

