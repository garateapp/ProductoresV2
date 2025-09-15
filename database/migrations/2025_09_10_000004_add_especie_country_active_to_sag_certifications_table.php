<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sag_certifications', function (Blueprint $table) {
            if (! Schema::hasColumn('sag_certifications', 'especie_id')) {
                $table->foreignId('especie_id')->nullable()->constrained('especies')->nullOnDelete()->after('certification_type');
            }
            if (! Schema::hasColumn('sag_certifications', 'country_id')) {
                $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete()->after('especie_id');
            }
            if (! Schema::hasColumn('sag_certifications', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('country_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sag_certifications', function (Blueprint $table) {
            if (Schema::hasColumn('sag_certifications', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('sag_certifications', 'country_id')) {
                $table->dropConstrainedForeignId('country_id');
            }
            if (Schema::hasColumn('sag_certifications', 'especie_id')) {
                $table->dropConstrainedForeignId('especie_id');
            }
        });
    }
};
