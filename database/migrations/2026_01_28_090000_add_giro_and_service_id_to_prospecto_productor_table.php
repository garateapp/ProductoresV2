<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospecto_productor', function (Blueprint $table) {
            $table->string('giro')->nullable()->after('tipo_empresa');
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete()->after('giro');
        });
    }

    public function down(): void
    {
        Schema::table('prospecto_productor', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn('giro');
        });
    }
};
