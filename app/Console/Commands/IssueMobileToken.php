<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IssueMobileToken extends Command
{
    protected $signature = 'tokens:issue-mobile 
        {user : ID o email del usuario} 
        {--name=campo-tag-mobile : Nombre del token} 
        {--abilities=field-sync : Abilidades separadas por coma (ej: field-sync,read)}';

    protected $description = 'Emite un token de acceso personal (Sanctum) para la app móvil de sincronización de campo.';

    public function handle(): int
    {
        $identifier = (string) $this->argument('user');
        $name = (string) $this->option('name');
        $abilitiesOption = (string) $this->option('abilities');

        $abilities = collect(explode(',', $abilitiesOption))
            ->map(fn ($ability) => trim($ability))
            ->filter()
            ->values()
            ->all();

        $user = User::query()
            ->when(str_contains($identifier, '@'), function ($query) use ($identifier) {
                $query->where('email', $identifier);
            }, function ($query) use ($identifier) {
                $query->whereKey($identifier);
            })
            ->first();

        if (! $user) {
            $this->error("No se encontró el usuario '{$identifier}'. Usa un ID o email válido.");
            return self::FAILURE;
        }

        $token = $user->createToken($name, $abilities)->plainTextToken;

        $this->info('Token creado correctamente:');
        $this->line($token);
        $this->newLine();
        $this->line('Configura EXPO_PUBLIC_API_TOKEN con el valor anterior en la app móvil.');

        return self::SUCCESS;
    }
}
