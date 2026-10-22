<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable("inventory_semi_finished_productions")) {
            Schema::create("inventory_semi_finished_productions", function (Blueprint $table) {
                $table->id();
                $table->foreignId("logistic_unit_id")->constrained("inventory_logistic_units")->cascadeOnDelete();
                $table->foreignId("technical_sheet_id")->constrained("inventory_technical_sheets");
                $table->foreignId("location_id")->constrained("inventory_locations");
                $table->foreignId("created_by")->constrained("users");
                $table->decimal("quantity", 18, 4);
                $table->enum("status", ["completed", "reverted"])->default("completed");
                $table->timestamp("reversed_at")->nullable();
                $table->foreignId("reversed_by_id")->nullable()->constrained("users");
                $table->text("reversal_reason")->nullable();
                $table->json("metadata")->nullable();
                $table->timestamps();

                $table->index(["logistic_unit_id"]);
                $table->index(["status"]);
            });

            return;
        }

        Schema::table("inventory_semi_finished_productions", function (Blueprint $table) {
            if (! Schema::hasColumn("inventory_semi_finished_productions", "status")) {
                $table->enum("status", ["completed", "reverted"])->default("completed");
            }
            if (! Schema::hasColumn("inventory_semi_finished_productions", "reversed_at")) {
                $table->timestamp("reversed_at")->nullable();
            }
            if (! Schema::hasColumn("inventory_semi_finished_productions", "reversed_by_id")) {
                $table->foreignId("reversed_by_id")->nullable()->constrained("users");
            }
            if (! Schema::hasColumn("inventory_semi_finished_productions", "reversal_reason")) {
                $table->text("reversal_reason")->nullable();
            }
            if (! Schema::hasColumn("inventory_semi_finished_productions", "metadata")) {
                $table->json("metadata")->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("inventory_semi_finished_productions");
    }
};
