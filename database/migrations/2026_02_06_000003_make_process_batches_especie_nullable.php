<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_batches', function (Blueprint $table) {
            if (Schema::hasColumn('process_batches', 'especie')) {
                $table->string('especie', 80)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('process_batches', function (Blueprint $table) {
            if (Schema::hasColumn('process_batches', 'especie')) {
                $table->string('especie', 80)->nullable(false)->change();
            }
        });
    }
};

