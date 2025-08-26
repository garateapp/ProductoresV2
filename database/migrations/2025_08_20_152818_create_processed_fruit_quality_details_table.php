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
        Schema::dropIfExists('processed_fruit_quality_details'); // Add this line to handle failed migrations
        Schema::create('processed_fruit_quality_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('processed_fruit_quality_id');
            $table->foreignId('parametro_id')->constrained()->onDelete('cascade');
            $table->foreignId('valor_id')->constrained()->onDelete('cascade');
            $table->integer('cantidad_muestra')->nullable();
            $table->decimal('porcentaje_muestra', 8, 2)->nullable();
            $table->string('categoria')->nullable(); // For 'Exportable'
            $table->decimal('temperatura', 8, 2)->nullable();
            $table->decimal('valor_ss', 8, 2)->nullable(); // For pressure
            $table->string('tipo_item')->nullable();
            $table->string('detalle_item')->nullable();
            $table->string('tipo_detalle')->nullable();
            $table->timestamps();

            $table->foreign('processed_fruit_quality_id', 'proc_qual_details_foreign')->references('id')->on('processed_fruit_qualities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_fruit_quality_details');
    }
};
