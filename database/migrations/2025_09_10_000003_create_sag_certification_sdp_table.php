<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sag_certification_sdp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sag_certification_id')->constrained('sag_certifications')->cascadeOnDelete();
            $table->foreignId('sdp_site_id')->constrained('sdp_sites')->cascadeOnDelete();
            $table->unique(['sag_certification_id', 'sdp_site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sag_certification_sdp');
    }
};
