<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdp_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('csg_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdp_sites');
    }
};
