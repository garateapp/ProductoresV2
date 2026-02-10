<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('folio_deductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('process_id')->comment('PKG_G_Produccion.id en SQL Server');
            $table->string('folio');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->unique(['process_id', 'folio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folio_deductions');
    }
};
