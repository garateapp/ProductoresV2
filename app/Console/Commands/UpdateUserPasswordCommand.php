<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class UpdateUserPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:user-password {email} {--password= : The new password} {--rounds= : The bcrypt rounds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update a user\'s password.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $newPassword = $this->option('password') ?: 'password'; // Default to 'password'
        $rounds = $this->option('rounds');

        $user = User::where('email', $email)->first();

        if ($user) {
            if ($rounds) {
                $user->password = Hash::driver('bcrypt')->setRounds($rounds)->make($newPassword);
            } else {
                $user->password = Hash::make($newPassword);
            }
            $user->save();
            $this->info('Password for ' . $email . ' updated successfully to "' . $newPassword . '' . ($rounds ? ' with ' . $rounds . ' rounds.' : '.'));
        } else {
            $this->error('User with email ' . $email . ' not found.');
        }
    }
}
