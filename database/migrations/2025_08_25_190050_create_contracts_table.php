<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('contract_file_path')->nullable();
            $table->date('fecha_contrato');
            $table->date('vencimiento');
            $table->float('comision');
            $table->string('flete_a_huerto');
            $table->boolean('rebate');
            $table->boolean('bonificacion');
            $table->boolean('tarifa_premium');
            $table->text('comparativa')->nullable();
            $table->boolean('descuento_fruta_comercial');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
