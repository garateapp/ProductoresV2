<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garces_sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->bigInteger('last_id')->nullable();
            $table->string('last_folio', 120)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garces_sync_states');
    }
};
