<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garces_code_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 40);
            $table->string('codigo_interno', 80);
            $table->string('nombre', 150)->nullable();
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tipo', 'codigo_interno'], 'ux_garces_code_catalogs_tipo_codigo');
            $table->index(['tipo', 'activo'], 'idx_garces_code_catalogs_tipo_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garces_code_catalogs');
    }
};
