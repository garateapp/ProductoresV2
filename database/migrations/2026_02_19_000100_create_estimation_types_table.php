<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimation_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 80)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('estimation_types')->insert([
            [
                'code' => 'principal_1',
                'name' => 'Principal 1',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'principal_2',
                'name' => 'Principal 2',
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $existingTypes = DB::table('estimation_versions')
            ->select('type')
            ->whereNotNull('type')
            ->where('type', '<>', '')
            ->distinct()
            ->pluck('type');

        foreach ($existingTypes as $code) {
            $code = strtolower(trim((string) $code));
            if ($code === '') {
                continue;
            }

            $exists = DB::table('estimation_types')
                ->where('code', $code)
                ->exists();

            if ($exists) {
                continue;
            }

            $name = Str::title(str_replace('_', ' ', $code));
            $nameExists = DB::table('estimation_types')
                ->where('name', $name)
                ->exists();
            if ($nameExists) {
                $name = Str::limit($name.' ('.$code.')', 80, '');
            }

            DB::table('estimation_types')->insert([
                'code' => $code,
                'name' => $name,
                'sort_order' => 100,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estimation_types');
    }
};
