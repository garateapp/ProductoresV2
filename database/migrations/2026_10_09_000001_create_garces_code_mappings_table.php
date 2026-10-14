<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garces_code_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 40);
            $table->string('clave_interna', 160);
            $table->string('clave_garces', 80);
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->integer('prioridad')->unsigned()->default(0);
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tipo', 'clave_interna', 'clave_garces'], 'ux_garces_code_mappings_key');
            $table->index(['tipo', 'activo'], 'idx_garces_code_mappings_tipo_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garces_code_mappings');
    }
};
