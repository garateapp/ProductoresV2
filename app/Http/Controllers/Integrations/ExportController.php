<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ExportController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'tipo']);

        $exports = IntegrationExport::with(['run.profile', 'createdBy'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->whereHas('run.profile', fn ($q) => $q->where('nombre', 'like', "%{$v}%")))
            ->when($filters['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($e) => [
                'id' => $e->id,
                'tipo' => $e->tipo,
                'archivo' => $e->archivo,
                'mime_type' => $e->mime_type,
                'tamano_bytes' => $e->tamano_bytes,
                'total_registros' => $e->total_registros,
                'perfil' => $e->run->profile?->nombre,
                'creador' => $e->createdBy?->name,
                'created_at' => $e->created_at?->format('Y-m-d H:i'),
                'can_download' => !is_null($e->archivo),
            ]);

        return Inertia::render('Integrations/Exports/Index', [
            'exports' => $exports,
            'filters' => $filters,
        ]);
    }

    public function download(IntegrationExport $export)
    {
        if (!$export->archivo || !Storage::disk($export->disk ?? 'local')->exists($export->archivo)) {
            return back()->with('error', 'El archivo no está disponible.');
        }

        return Storage::disk($export->disk ?? 'local')->download(
            $export->archivo,
            $export->archivo
        );
    }
}
