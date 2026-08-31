<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->constraintMissing('inventory_tech_equipment_delivery_act_items', 'fk_inv_tech_item_act')) {
            Schema::table('inventory_tech_equipment_delivery_act_items', function (Blueprint $table) {
                $table->foreign('delivery_act_id', 'fk_inv_tech_item_act')
                    ->references('id')
                    ->on('inventory_tech_equipment_delivery_acts')
                    ->cascadeOnDelete();
            });
        }

        if ($this->constraintMissing('inventory_tech_equipment_delivery_act_items', 'fk_inv_tech_item_equipment')) {
            Schema::table('inventory_tech_equipment_delivery_act_items', function (Blueprint $table) {
                $table->foreign('equipment_id', 'fk_inv_tech_item_equipment')
                    ->references('id')
                    ->on('inventory_tech_equipment');
            });
        }
    }

    public function down(): void
    {
        Schema::table('inventory_tech_equipment_delivery_act_items', function (Blueprint $table) {
            $table->dropForeign('fk_inv_tech_item_act');
            $table->dropForeign('fk_inv_tech_item_equipment');
        });
    }

    private function constraintMissing(string $table, string $name): bool
    {
        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?",
            [$table, $name]
        );

        return $exists === null;
    }
};
