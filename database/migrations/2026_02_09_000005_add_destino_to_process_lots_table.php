<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            $table->string('destino', 60)->nullable()->after('setup_hash');
            $table->index(['packing_line_id', 'destino']);
        });
    }

    public function down(): void
    {
        Schema::table('process_lots', function (Blueprint $table) {
            $table->dropIndex(['packing_line_id', 'destino']);
            $table->dropColumn('destino');
        });
    }
};

