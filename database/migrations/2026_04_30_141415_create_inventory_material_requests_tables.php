<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asociación de usuarios a ubicaciones (quién recibe las solicitudes)
        if (!Schema::hasTable('inventory_location_user')) {
            Schema::create('inventory_location_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
            });
        }

        // Cabecera de solicitud de materiales
        Schema::create('inventory_material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // Folio de solicitud
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('origin_location_id')->constrained('inventory_locations'); // De donde se pide
            $table->foreignId('destination_location_id')->constrained('inventory_locations'); // Para donde es
            $table->string('estado')->default('pendiente'); // pendiente, aprobado, rechazado, completado
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->timestamp('fecha_requerida')->nullable();
            $table->timestamps();
        });

        // Detalle de solicitud
        Schema::create('inventory_material_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_id')->constrained('inventory_material_requests')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->decimal('cantidad_solicitada', 18, 4);
            $table->decimal('cantidad_entregada', 18, 4)->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_material_request_items');
        Schema::dropIfExists('inventory_material_requests');
        // No borramos inventory_location_user si ya existía antes de esta migración
    }
};
