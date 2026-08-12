<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_technical_sheets', function (Blueprint $table): void {
            $table->string('nombre', 200)->nullable()->after('es_semielaborado');
        });

        DB::table('inventory_technical_sheets')
            ->whereNull('nombre')
            ->orderBy('id')
            ->eachById(function (object $sheet): void {
                DB::table('inventory_technical_sheets')
                    ->where('id', $sheet->id)
                    ->update(['nombre' => 'Ficha técnica #'.$sheet->id]);
            });

        Schema::create('inventory_technical_sheet_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('technical_sheet_id')
                ->constrained('inventory_technical_sheets')
                ->cascadeOnDelete();
            $table->string('disk', 50)->default('public');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('descripcion');
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['technical_sheet_id', 'orden'], 'idx_inv_sheet_images_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_technical_sheet_images');

        Schema::table('inventory_technical_sheets', function (Blueprint $table): void {
            $table->dropColumn('nombre');
        });
    }
};
