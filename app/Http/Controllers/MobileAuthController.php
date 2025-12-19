<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    /**
     * Devuelve un token Sanctum para la app móvil al autenticarse con email y password.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower($credentials['email']);
        $limiterKey = 'login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Intenta más tarde.',
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($limiterKey);

            throw ValidationException::withMessages([
                'email' => 'Credenciales inválidas.',
            ]);
        }

        RateLimiter::clear($limiterKey);

        // Opcional: limpia tokens anteriores con el mismo nombre para este usuario.
        $user->tokens()->where('name', 'campo-tag-mobile')->delete();

        $token = $user->createToken('campo-tag-mobile', ['field-sync'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
