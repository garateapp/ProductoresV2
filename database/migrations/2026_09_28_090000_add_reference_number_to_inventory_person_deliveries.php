<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SEQUENCE_KEY = 'inventory.person_delivery.referencia';

    public function up(): void
    {
        Schema::create('inventory_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });

        Schema::table('inventory_person_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('numero_referencia')->nullable()->after('codigo');
        });

        // Las actas existentes se ordenan por su código (timestamp de creación) para que
        // el correlativo respete el orden real de emisión y no el id de inserción.
        $next = 1;
        $timestamp = now();

        DB::table('inventory_person_deliveries')
            ->orderBy('codigo')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$next, $timestamp): void {
                DB::table('inventory_person_deliveries')
                    ->where('id', $id)
                    ->update(['numero_referencia' => $next++, 'updated_at' => $timestamp]);
            });

        $maxAssigned = $next - 1;

        DB::table('inventory_number_sequences')->insert([
            'key' => self::SEQUENCE_KEY,
            'value' => $maxAssigned,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('inventory_person_deliveries', function (Blueprint $table) {
            $table->unique('numero_referencia', 'uq_inventory_person_delivery_numero_referencia');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_person_deliveries', function (Blueprint $table) {
            $table->dropUnique('uq_inventory_person_delivery_numero_referencia');
            $table->dropColumn('numero_referencia');
        });

        DB::table('inventory_number_sequences')->where('key', self::SEQUENCE_KEY)->delete();

        Schema::dropIfExists('inventory_number_sequences');
    }
};
