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
        Schema::create('quality_control_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calidad_id')->constrained('calidads')->onDelete('cascade');
            $table->foreignId('photo_type_id')->constrained('photo_types')->onDelete('cascade');
            $table->string('path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_control_photos');
    }
};
