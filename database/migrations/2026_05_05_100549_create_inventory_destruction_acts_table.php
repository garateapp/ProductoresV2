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
        Schema::create('inventory_destruction_acts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_record_id')->constrained('inventory_waste_records');
            $table->foreignId('user_id')->constrained('users');
            $table->string('folio')->unique();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_destruction_acts');
    }
};
