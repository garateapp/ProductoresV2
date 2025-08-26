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
        Schema::dropIfExists('processed_fruit_quality_photos'); // Add this line to handle failed migrations
        Schema::create('processed_fruit_quality_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('processed_fruit_quality_id');
            $table->foreignId('photo_type_id')->constrained()->onDelete('cascade');
            $table->string('photo_path');
            $table->timestamps();

            $table->foreign('processed_fruit_quality_id', 'proc_qual_photos_foreign')->references('id')->on('processed_fruit_qualities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_fruit_quality_photos');
    }
};
