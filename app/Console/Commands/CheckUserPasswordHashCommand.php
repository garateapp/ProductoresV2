<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckUserPasswordHashCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:user-password-hash {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks a user\'s password hash.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $this->info('User found. Password hash: ' . $user->password);
        } else {
            $this->error('User with email ' . $email . ' not found.');
        }
    }
}
