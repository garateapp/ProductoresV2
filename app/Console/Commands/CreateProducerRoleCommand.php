<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CreateProducerRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:producer-role';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates the producer role if it does not exist.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Role::firstOrCreate(['name' => 'producer']);
        $this->info('Producer role created or already exists.');
    }
}
