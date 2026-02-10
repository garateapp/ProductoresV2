<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            $table->json('extra_packagings')->nullable()->after('cp2_cajas_por_pallet');
            $table->text('packaging_indications')->nullable()->after('extra_packagings');
        });
    }

    public function down(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            $table->dropColumn(['extra_packagings', 'packaging_indications']);
        });
    }
};

