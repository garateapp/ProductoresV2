<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(DB::select('SHOW INDEX FROM producer_csgs'))
            ->pluck('Key_name')
            ->unique()
            ->values();

        if (! $indexes->contains('producer_csgs_user_id_index')) {
            DB::statement('CREATE INDEX producer_csgs_user_id_index ON producer_csgs (user_id)');
        }

        Schema::table('producer_csgs', function (Blueprint $table) {
            $table->dropUnique('producer_csgs_user_id_csg_code_unique');

            $table->string('sdp', 50)->nullable()->after('csg_code');
            $table->string('variedad', 120)->nullable()->after('sdp');
            $table->boolean('sdp_validado')->default(false)->after('variedad');
            $table->timestamp('sdp_validado_at')->nullable()->after('sdp_validado');

            $table->unique(['user_id', 'csg_code', 'variedad']);
        });
    }

    public function down(): void
    {
        Schema::table('producer_csgs', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'csg_code', 'variedad']);
            $table->dropColumn(['sdp', 'variedad', 'sdp_validado', 'sdp_validado_at']);
            $table->unique(['user_id', 'csg_code']);
        });
    }
};
