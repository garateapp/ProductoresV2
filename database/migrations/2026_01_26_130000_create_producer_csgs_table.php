<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producer_csgs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('idprod', 50)->nullable();
            $table->string('csg_code', 60);
            $table->string('predio_name', 255)->nullable();
            $table->string('predio_direccion', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'csg_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producer_csgs');
    }
};
