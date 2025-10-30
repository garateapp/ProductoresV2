<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->double('descuento_hidrocooler')->nullable()->after('descuento_fruta_comercial');
            $table->double('porcentaje_descuento_fruta_comercial')->nullable()->after('descuento_hidrocooler');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['descuento_hidrocooler', 'porcentaje_descuento_fruta_comercial']);
        });
    }
};
