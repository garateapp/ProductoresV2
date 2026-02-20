<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return null; // Temporarily disable Inertia version checking
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? $request->user()->load('roles') : null,
            ],
            'import_errors' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return null;
                }

                return cache()->pull('import_errors:'.$user->id);
            },
            'import_feedback' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return null;
                }

                return cache()->pull('import_feedback:'.$user->id);
            },
            'unread_notifications_count' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'sync_output' => fn () => $request->session()->get('sync_output'),
                'calidad_id' => fn () => $request->session()->get('calidad_id'),
            ],
            'ziggy' => function () use ($request) {
                return array_merge((new \Tighten\Ziggy\Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
        ]);
    }
}
