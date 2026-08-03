<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_clients', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 200);
            $table->string('rut', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('contacto', 200)->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_clients');
    }
};
