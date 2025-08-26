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
        Schema::table('markets', function (Blueprint $table) {
            $table->unsignedBigInteger('authorization_type_id')->nullable()->after('is_active');
            $table->foreign('authorization_type_id')->references('id')->on('authorization_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropForeign(['authorization_type_id']);
            $table->dropColumn('authorization_type_id');
        });
    }
};