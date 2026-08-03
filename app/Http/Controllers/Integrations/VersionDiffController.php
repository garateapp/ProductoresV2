<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationProfileVersion;
use App\Models\IntegrationProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VersionDiffController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'profile_id']);

        $versions = IntegrationProfileVersion::with(['profile', 'createdBy'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->whereHas('profile', fn ($q) => $q->where('nombre', 'like', "%{$v}%")))
            ->when($filters['profile_id'] ?? null, fn ($q, $v) => $q->where('profile_id', $v))
            ->latest('version')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($v) => [
                'id' => $v->id,
                'profile' => $v->profile?->nombre,
                'profile_id' => $v->profile_id,
                'version' => $v->version,
                'estado' => $v->estado,
                'inmutable' => $v->inmutable,
                'creador' => $v->createdBy?->name,
                'created_at' => $v->created_at?->format('Y-m-d H:i'),
                'published_at' => $v->published_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Integrations/Compare/Index', [
            'versions' => $versions,
            'filters' => $filters,
            'profiles' => IntegrationProfile::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function show(IntegrationProfileVersion $version)
    {
        $version->load(['profile', 'inputFields', 'outputFields', 'rules', 'createdBy']);

        return Inertia::render('Integrations/Compare/Show', [
            'version' => [
                'id' => $version->id,
                'profile' => $version->profile?->nombre,
                'version' => $version->version,
                'estado' => $version->estado,
                'descripcion' => $version->descripcion,
                'creador' => $version->createdBy?->name,
                'created_at' => $version->created_at?->format('Y-m-d H:i'),
                'published_at' => $version->published_at?->format('Y-m-d H:i'),
                'input_fields' => $version->inputFields->map(fn ($f) => [
                    'id' => $f->id,
                    'clave' => $f->clave,
                    'etiqueta' => $f->etiqueta,
                    'tipo_dato' => $f->tipo_dato,
                    'obligatorio' => $f->obligatorio,
                    'posicion' => $f->posicion,
                ]),
                'output_fields' => $version->outputFields->map(fn ($f) => [
                    'id' => $f->id,
                    'clave_externa' => $f->clave_externa,
                    'etiqueta' => $f->etiqueta,
                    'tipo_dato' => $f->tipo_dato,
                    'obligatorio' => $f->obligatorio,
                    'posicion' => $f->posicion,
                ]),
                'rules' => $version->rules->map(fn ($r) => [
                    'id' => $r->id,
                    'tipo' => $r->tipo,
                    'nombre' => $r->nombre,
                    'orden' => $r->orden,
                    'config' => $r->config,
                ]),
            ],
            'previous_version' => IntegrationProfileVersion::where('profile_id', $version->profile_id)
                ->where('version', $version->version - 1)
                ->with(['inputFields', 'outputFields', 'rules'])
                ->first(),
        ]);
    }

    public function diff(Request $request, IntegrationProfileVersion $version)
    {
        $request->validate([
            'compare_with' => 'required|exists:integration_profile_versions,id',
        ]);

        $compareWith = IntegrationProfileVersion::with(['inputFields', 'outputFields', 'rules'])
            ->findOrFail($request->compare_with);

        $inputFieldsAdded = $version->inputFields->pluck('clave')->diff($compareWith->inputFields->pluck('clave'));
        $inputFieldsRemoved = $compareWith->inputFields->pluck('clave')->diff($version->inputFields->pluck('clave'));

        $outputFieldsAdded = $version->outputFields->pluck('clave_externa')->diff($compareWith->outputFields->pluck('clave_externa'));
        $outputFieldsRemoved = $compareWith->outputFields->pluck('clave_externa')->diff($version->outputFields->pluck('clave_externa'));

        return back()->with('diff', [
            'input_fields_added' => $inputFieldsAdded->values(),
            'input_fields_removed' => $inputFieldsRemoved->values(),
            'output_fields_added' => $outputFieldsAdded->values(),
            'output_fields_removed' => $outputFieldsRemoved->values(),
            'version_a' => $compareWith->id,
            'version_b' => $version->id,
        ]);
    }
}
