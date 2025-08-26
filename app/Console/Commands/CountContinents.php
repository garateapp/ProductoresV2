<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Continent; // Added

class CountContinents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:count-continents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Count the number of continents in the database.'; // Updated description

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = Continent::count(); // Modified
        $this->info("Number of continents: " . $count); // Modified
    }
}
