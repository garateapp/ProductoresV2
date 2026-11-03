<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garces_produccion_envios', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 100)->unique();
            $table->string('numero_proceso', 100)->nullable();
            $table->dateTime('fecha_proceso')->nullable();
            $table->unsignedInteger('records')->default(0);
            $table->decimal('cajas', 18, 4)->default(0);
            $table->decimal('kilos', 18, 4)->default(0);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('sent_at');
            $table->index('fecha_proceso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garces_produccion_envios');
    }
};
