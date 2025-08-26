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
        Schema::create('processed_fruit_qualities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained()->onDelete('cascade');
            $table->date('fecha')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('responsable')->nullable();
            $table->string('estado')->nullable();
            $table->text('firma_productor')->nullable();
            $table->text('firma_responsable')->nullable();
            $table->integer('t_muestra')->nullable();
            $table->string('materia_vegetal')->nullable();
            $table->string('piedras')->nullable();
            $table->string('barro')->nullable();
            $table->string('pedicelo_largo')->nullable();
            $table->string('racimo')->nullable();
            $table->string('esponjas')->nullable();
            $table->string('h_esponjas')->nullable();
            $table->string('llenado_tottes')->nullable();
            $table->integer('embalaje')->nullable();
            $table->text('obs_ext')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_fruit_qualities');
    }
};
