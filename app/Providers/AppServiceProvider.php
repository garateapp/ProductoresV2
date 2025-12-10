<?php

namespace App\Providers;

use App\Models\LoginEvent;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Event::listen(Login::class, function (Login $event): void {
            try {
                LoginEvent::create([
                    'user_id' => $event->user->id,
                    'ip_address' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to log login event', ['error' => $e->getMessage()]);
            }
        });
    }
}
