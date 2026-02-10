<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IssueFolioToken extends Command
{
    protected $signature = 'token:folio {user_id}';

    protected $description = 'Genera un token Sanctum para el Lector de Folios';

    public function handle(): void
    {
        $user = User::findOrFail($this->argument('user_id'));

        $user->tokens()->where('name', 'lector-folios')->delete();

        $token = $user->createToken('lector-folios', ['folio-deduct'])->plainTextToken;

        $this->info("Token generado para {$user->name}:");
        $this->line($token);
    }
}
