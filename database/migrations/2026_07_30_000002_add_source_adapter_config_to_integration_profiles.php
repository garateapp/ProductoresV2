<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_profiles', function (Blueprint $table) {
            $table->json('source_adapter_config')->nullable()->after('source_adapter');
        });
    }

    public function down(): void
    {
        Schema::table('integration_profiles', function (Blueprint $table) {
            $table->dropColumn('source_adapter_config');
        });
    }
};
