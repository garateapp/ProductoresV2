<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pallet_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->index();
            $table->string('c_embalaje')->index();
            $table->foreignId('technical_sheet_id')
                ->constrained('inventory_technical_sheets');
            $table->foreignId('user_id')
                ->constrained('users');
            $table->enum('resultado', ['aprobado', 'rechazado']);
            $table->text('observaciones_generales')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_inspections');
    }
};
