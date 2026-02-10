<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging_matrix_rules', function (Blueprint $table) {
            $table->id();

            $table->string('matrix', 40)->default('carozos');
            $table->string('especie', 80);
            $table->string('destino', 60)->nullable();
            $table->string('nota', 60)->nullable();
            $table->string('variedad', 120)->nullable();
            $table->string('color', 120)->nullable();
            $table->boolean('require_sdp')->default(false);

            $table->string('c_item', 60);
            $table->string('desc_embalaje', 220)->nullable();
            $table->decimal('peso_caja', 6, 2)->nullable();

            // Lista de calibres permitidos (ej: [28,30,32,...])
            $table->json('allowed_calibres')->nullable();

            // Texto informativo (ej: "CALIBRES POR SERIES ...") para uso humano.
            $table->text('calibres_note')->nullable();
            $table->string('sobre_calibre_note', 220)->nullable();

            // Orden de evaluación: menor = primero.
            $table->unsignedInteger('priority')->default(1000);
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index(['matrix', 'activo', 'priority'], 'idx_pack_matrix_active_priority');
            $table->index(['matrix', 'especie', 'destino'], 'idx_pack_matrix_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging_matrix_rules');
    }
};

