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
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certifying_house_id')->constrained('certifying_houses')->onDelete('cascade');
            $table->string('name');
            $table->foreignId('certificate_type_id')->constrained('certificate_types')->onDelete('cascade');
            $table->string('certificate_code')->unique();
            $table->foreignId('especie_id')->constrained('especies')->onDelete('cascade');
            $table->date('audit_date');
            $table->date('expiration_date');
            $table->string('certificate_pdf_path')->nullable();
            $table->boolean('has_pdf_extension')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
