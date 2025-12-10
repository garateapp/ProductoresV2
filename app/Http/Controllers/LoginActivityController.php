<?php

namespace App\Http\Controllers;

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoginActivityController extends Controller
{
    public function index(Request $request)
    {
        $users = User::select('id', 'name', 'email')
            ->withCount('loginEvents')
            ->addSelect([
                'last_login_at' => LoginEvent::select('created_at')
                    ->whereColumn('user_id', 'users.id')
                    ->latest()
                    ->limit(1),
            ])
            ->orderByDesc('login_events_count')
            ->paginate(20)
            ->withQueryString();

        $totalLogins = LoginEvent::count();

        return Inertia::render('Admin/LoginActivity/Index', [
            'users' => $users,
            'totalLogins' => $totalLogins,
        ]);
    }
}
