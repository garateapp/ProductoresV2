<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_source_adapters', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->string('tipo_conexion', 50);
            $table->json('configuracion');
            $table->json('esquema_entrada')->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_source_adapters');
    }
};
