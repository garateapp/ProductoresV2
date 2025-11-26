<?php

namespace App\Http\Controllers;

use App\Models\FieldVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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
            'audio' => ['required', 'file', 'max:20480', 'mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a,audio/aac,audio/webm,audio/ogg'],
            'visited_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $apiKey = config('services.assemblyai.key');
        if (! $apiKey) {
            return back()->with('error', 'Falta configurar ASSEMBLYAI_API_KEY en el servidor.');
        }

        $audioFile = $request->file('audio');

        try {
            $uploadUrl = $this->uploadToAssembly($audioFile, $apiKey);
            $transcript = $this->transcribeWithAssembly($uploadUrl, $apiKey);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo transcribir el audio: ' . $e->getMessage());
        }

        if (! $transcript) {
            return back()->with('error', 'Transcripción vacía o no disponible.');
        }

        FieldVisit::create([
            'user_id' => Auth::id(),
            'visited_at' => $validated['visited_at'] ?? now(),
            'transcript' => $transcript,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        return back()->with('success', 'Visita registrada y transcrita correctamente.');
    }

    private function uploadToAssembly(\Illuminate\Http\UploadedFile $file, string $apiKey): string
    {
        $response = Http::withHeaders([
            'authorization' => $apiKey,
            'transfer-encoding' => 'chunked',
        ])->withBody(
            file_get_contents($file->getRealPath()),
            'application/octet-stream'
        )->post('https://api.assemblyai.com/v2/upload');

        if (! $response->ok() || empty($response->json('upload_url'))) {
            throw new \RuntimeException('Fallo al subir el audio a AssemblyAI');
        }

        return $response->json('upload_url');
    }

    private function transcribeWithAssembly(string $audioUrl, string $apiKey): string
    {
        $start = Http::withHeaders([
            'authorization' => $apiKey,
            'content-type' => 'application/json',
        ])->post('https://api.assemblyai.com/v2/transcript', [
            'audio_url' => $audioUrl,
            'format_text' => true,
        ]);

        if (! $start->ok() || empty($start->json('id'))) {
            throw new \RuntimeException('No se pudo iniciar la transcripción');
        }

        $transcriptId = $start->json('id');
        $pollUrl = "https://api.assemblyai.com/v2/transcript/{$transcriptId}";

        // Poll up to ~60s
        $attempts = 0;
        while ($attempts < 20) {
            sleep(3);
            $attempts++;
            $poll = Http::withHeaders([
                'authorization' => $apiKey,
            ])->get($pollUrl);

            if (! $poll->ok()) {
                continue;
            }

            $status = $poll->json('status');
            if ($status === 'completed') {
                return (string) ($poll->json('text') ?? '');
            }

            if ($status === 'error') {
                $msg = $poll->json('error') ?? 'Error desconocido';
                throw new \RuntimeException($msg);
            }
        }

        throw new \RuntimeException('La transcripción tardó demasiado.');
    }
}
