<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_mapping_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('integration_clients');
            $table->string('codigo', 50);
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('borrador'); // borrador, publicado, inactivo, archivado
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_mapping_sets');
    }
};
