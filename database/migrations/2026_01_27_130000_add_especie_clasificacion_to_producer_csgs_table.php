<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producer_csgs', function (Blueprint $table) {
            $table->string('especie', 120)->nullable()->after('variedad');
            $table->string('clasificacion', 20)->nullable()->after('especie');
        });
    }

    public function down(): void
    {
        Schema::table('producer_csgs', function (Blueprint $table) {
            $table->dropColumn(['especie', 'clasificacion']);
        });
    }
};
