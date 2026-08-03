<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->index();
            $table->string('email', 150)->unique();
            $table->string('cargo', 150)->nullable();
            $table->timestamps();
        });

        Schema::table('inventory_person_deliveries', function (Blueprint $table) {
            $table->foreignId('person_id')
                ->nullable()
                ->after('origin_location_id')
                ->constrained('personal')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_person_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::dropIfExists('personal');
    }
};
