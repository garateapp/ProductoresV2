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
        Schema::table('sag_certifications', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['user_id']);
            // Drop the column
            $table->dropColumn('user_id');

            // Add producer_rut column
            $table->string('producer_rut')->after('id'); // Place it after 'id'
            $table->index('producer_rut'); // Add an index for faster lookups
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sag_certifications', function (Blueprint $table) {
            // Drop producer_rut column
            $table->dropIndex(['producer_rut']);
            $table->dropColumn('producer_rut');

            // Re-add user_id column (nullable for now, data would need to be re-populated if rolling back)
            $table->foreignId('user_id')->nullable()->after('id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};