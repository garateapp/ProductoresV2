<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sag_certifications', function (Blueprint $table) {
            if (! Schema::hasColumn('sag_certifications', 'csg_user_id')) {
                $table->foreignId('csg_user_id')->nullable()->constrained('users')->nullOnDelete()->after('producer_rut');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sag_certifications', function (Blueprint $table) {
            if (Schema::hasColumn('sag_certifications', 'csg_user_id')) {
                $table->dropConstrainedForeignId('csg_user_id');
            }
        });
    }
};
