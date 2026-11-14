<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garces_produccion_envios', function (Blueprint $table) {
            $table->longText('json_enviado')->nullable()->after('last_error');
            $table->longText('respuesta_api')->nullable()->after('json_enviado');
        });
    }

    public function down(): void
    {
        Schema::table('garces_produccion_envios', function (Blueprint $table) {
            $table->dropColumn(['json_enviado', 'respuesta_api']);
        });
    }
};
