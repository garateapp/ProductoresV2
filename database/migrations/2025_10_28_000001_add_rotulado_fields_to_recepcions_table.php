<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            $table->unsignedBigInteger('id_productor_rotulado')->nullable()->after('Codigo_Sag_emisor');
            $table->string('n_productor_rotulado')->nullable()->after('id_productor_rotulado');
            $table->string('csg_productor_rotulado')->nullable()->after('n_productor_rotulado');
        });
    }

    public function down(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            $table->dropColumn([
                'id_productor_rotulado',
                'n_productor_rotulado',
                'csg_productor_rotulado',
            ]);
        });
    }
};
