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
        Schema::create('sag_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Links to the User (CSG)
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_path'); // Path to the uploaded PDF
            $table->date('issue_date');
            $table->date('expiration_date')->nullable();
            $table->string('certification_type'); // e.g., 'Authorization', 'Certification'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sag_certifications');
    }
};