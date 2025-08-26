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
        Schema::create('mrl_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('especie_id')->constrained()->onDelete('cascade');
            $table->foreignId('variedad_id')->constrained()->onDelete('cascade');
            $table->string('csg');
            $table->string('laboratory');
            $table->date('sampling_date');
            $table->string('result_file_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mrl_samples');
    }
};
