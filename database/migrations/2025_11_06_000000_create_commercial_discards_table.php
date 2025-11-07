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
        Schema::create('commercial_discards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('fecha')->useCurrent();
            $table->string('linea', 50);
            $table->string('turno', 50);
            $table->string('productor');
            $table->string('especie');
            $table->string('variedad');
            $table->string('lote', 100);
            $table->string('proceso', 100);
            $table->integer('frutos')->default(0);
            $table->text('observaciones')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercial_discards');
    }
};
