<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('integration_runs')->cascadeOnDelete();
            $table->string('tipo', 50)->default('excel'); // excel, csv, json
            $table->string('archivo', 500);
            $table->string('disk', 50)->default('local');
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('tamano_bytes')->unsigned()->nullable();
            $table->integer('total_registros')->unsigned()->default(0);
            $table->string('hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_exports');
    }
};
