<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            InventoryModulePermissionsSeeder::class,
            IntegrationModulePermissionsSeeder::class,
        ]);

        // User::factory(10)->create();

        // if (! User::where('email', 'admin@admin.com')->exists()) {
        //     User::factory()->create([
        //         'name' => 'Test User',
        //         'email' => 'admin@admin.com',
        //         'password' => Hash::make('password'),
        //     ]);
        // }
    }
}
