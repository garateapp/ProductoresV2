<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_tech_equipment', function (Blueprint $table) {
            $table->id();
            $table->string('marca', 150);
            $table->date('fecha')->nullable();
            $table->string('numero_serie', 120)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['marca', 'numero_serie'], 'idx_inv_tech_equipment_marca_serie');
        });

        Schema::create('inventory_tech_equipment_delivery_acts', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->string('person_name', 150);
            $table->string('person_rut', 20)->nullable();
            $table->string('departamento', 150)->nullable();
            $table->string('cargo', 150)->nullable();
            $table->string('condicion', 20);
            $table->dateTime('delivered_at');
            $table->longText('signature_data_url');
            $table->text('observations')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->text('return_observations')->nullable();
            $table->longText('return_signature_data_url')->nullable();
            $table->timestamps();

            $table->index(['delivered_at', 'created_by'], 'idx_inv_tech_delivery_act_date_user');
        });

        Schema::create('inventory_tech_equipment_delivery_act_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_act_id');
            $table->unsignedBigInteger('equipment_id');
            $table->timestamps();

            $table->foreign('delivery_act_id', 'fk_inv_tech_item_act')
                ->references('id')
                ->on('inventory_tech_equipment_delivery_acts')
                ->cascadeOnDelete();
            $table->foreign('equipment_id', 'fk_inv_tech_item_equipment')
                ->references('id')
                ->on('inventory_tech_equipment');

            $table->index(['equipment_id', 'delivery_act_id'], 'idx_inv_tech_delivery_item_equipment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_tech_equipment_delivery_act_items');
        Schema::dropIfExists('inventory_tech_equipment_delivery_acts');
        Schema::dropIfExists('inventory_tech_equipment');
    }
};
