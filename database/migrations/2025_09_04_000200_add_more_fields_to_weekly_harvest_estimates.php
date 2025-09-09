<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('weekly_harvest_estimates', function (Blueprint $table) {
            $table->boolean('acopio')->nullable()->after('notes');
            $table->boolean('radio_mosca')->nullable()->after('acopio');
            $table->boolean('corea_greenex')->nullable()->after('radio_mosca');
            $table->string('tipo_cereza', 32)->nullable()->after('corea_greenex');
            $table->index(['acopio','radio_mosca','corea_greenex'], 'idx_estimates_flags');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_harvest_estimates', function (Blueprint $table) {
            $table->dropIndex('idx_estimates_flags');
            $table->dropColumn(['acopio','radio_mosca','corea_greenex','tipo_cereza']);
        });
    }
};

