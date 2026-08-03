<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationProfile;
use App\Models\IntegrationProfileVersion;
use App\Services\Integrations\Engine\TransformationEngine;
use App\Services\Integrations\Engine\SourceAdapterFactory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SimulatorController extends Controller
{
    public function index(Request $request)
    {
        $profiles = IntegrationProfile::with('currentVersion')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'codigo' => $p->codigo,
                'version' => $p->currentVersion?->version,
                'direccion' => $p->direccion,
            ]);

        return Inertia::render('Integrations/Simulator/Index', [
            'profiles' => $profiles,
        ]);
    }

    public function preview(Request $request, TransformationEngine $engine)
    {
        $data = $request->validate([
            'profile_id' => 'required|exists:integration_profiles,id',
            'payload' => 'required|array',
            'payload.*' => 'array',
        ]);

        $profile = IntegrationProfile::with('currentVersion')->findOrFail($data['profile_id']);
        $version = $profile->currentVersion;

        if (!$version) {
            return back()->with('error', 'El perfil no tiene una versión activa.');
        }

        $adapter = SourceAdapterFactory::create($profile->source_adapter ?? 'internal_database');

        $normalized = collect($data['payload'])->map(fn ($row) => $row);

        $results = $normalized->map(function ($input) use ($engine, $version) {
            $result = $engine->process($input, $version);

            return [
                'input' => $input,
                'output' => $result->output,
                'rules_trace' => $result->rulesTrace,
                'errors' => $result->errors,
                'warnings' => $result->warnings,
                'success' => $result->success,
            ];
        });

        return Inertia::render('Integrations/Simulator/Results', [
            'profile_nombre' => $profile->nombre,
            'profile_codigo' => $profile->codigo,
            'results' => $results,
        ]);
    }
}
