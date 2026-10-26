<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
{
    Log::info('checkpallet.login.request', [
        'login' => $request->input('login'),
        'email' => $request->input('email'),
        'username' => $request->input('username'),
        'user' => $request->input('user'),
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'has_password' => $request->filled('password'),
    ]);

    // Normalizar: aceptar login, email, username o user
    $login = $request->input('login')
        ?? $request->input('email')
        ?? $request->input('username')
        ?? $request->input('user');

    if ($login && ! $request->has('login')) {
        $request->merge(['login' => $login]);
    }

    $credentials = $request->validate([
        'login' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    $login = trim($credentials['login']);
    $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    $field = $fieldType === 'email' ? 'email' : 'user';

    if ($fieldType === 'email') {
        $login = strtolower($login);
    }

    Log::info('checkpallet.login.lookup', [
        'field' => $field,
        'login' => $login,
    ]);

    $user = User::query()->where($field, $login)->first();

    Log::info('checkpallet.login.lookup.result', [
        'found' => (bool) $user,
        'user_id' => $user?->id,
    ]);

    if (! $user || ! Hash::check($credentials['password'], $user->password)) {
        return response()->json([
            'message' => 'Credenciales inválidas.',
        ], 401);
    }

    $token = $user->createToken('checkpallet-mobile')->plainTextToken;

    return response()->json([
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->user,
        ],
        'token' => $token,
    ]);
}

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}
