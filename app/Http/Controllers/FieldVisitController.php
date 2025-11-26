<?php

namespace App\Http\Controllers;

use App\Models\FieldVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class FieldVisitController extends Controller
{
    private function ensureCanAccess(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole(['Administrador', 'Agronomo'])) {
            abort(403);
        }
    }

    public function index(Request $request): Response
    {
        $this->ensureCanAccess();

        $query = FieldVisit::with('user')->latest();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('transcript', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $visits = $query->paginate(10)->withQueryString();

        return Inertia::render('Agronomos/FieldVisits/Index', [
            'visits' => $visits->through(function (FieldVisit $visit) {
                return [
                    'id' => $visit->id,
                    'user' => [
                        'id' => $visit->user->id,
                        'name' => $visit->user->name,
                        'email' => $visit->user->email,
                    ],
                    'visited_at' => optional($visit->visited_at)->format('Y-m-d H:i:s'),
                    'transcript' => $visit->transcript,
                    'latitude' => $visit->latitude,
                    'longitude' => $visit->longitude,
                ];
            }),
            'filters' => [
                'search' => $search ?? '',
            ],
            'assemblyai' => [
                'api_key' => config('services.assemblyai.key'),
                'host' => config('services.assemblyai.stream_host', 'streaming.assemblyai.com'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureCanAccess();

        $validated = $request->validate([
            'transcript' => ['required', 'string', 'max:20000'],
            'visited_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        FieldVisit::create([
            'user_id' => Auth::id(),
            'visited_at' => $validated['visited_at'] ?? now(),
            'transcript' => $validated['transcript'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        return back()->with('success', 'Visita registrada correctamente.');
    }
}
