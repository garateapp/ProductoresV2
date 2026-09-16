<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->string('area', 150)->nullable()->after('cargo');
        });

        Schema::table('inventory_person_deliveries', function (Blueprint $table) {
            $table->string('person_area', 150)->nullable()->after('person_position');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_person_deliveries', function (Blueprint $table) {
            $table->dropColumn('person_area');
        });

        Schema::table('personal', function (Blueprint $table) {
            $table->dropColumn('area');
        });
    }
};
