<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationClient;
use App\Models\IntegrationProfile;
use App\Models\IntegrationProfileVersion;
use App\Enums\IntegrationProfileStatus;
use App\Services\Integrations\Engine\SourceAdapterFactory;
use App\Services\Integrations\Engine\OutputExporterFactory;
use App\Services\Integrations\Audit\IntegrationAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', IntegrationProfile::class);
        $filters = $request->only(['q', 'estado', 'client_id', 'direccion']);

        $profiles = IntegrationProfile::with(['client', 'currentVersion', 'createdBy'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nombre', 'like', "%{$v}%")
                    ->orWhere('codigo', 'like', "%{$v}%");
            }))
            ->when($filters['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filters['client_id'] ?? null, fn ($q, $v) => $q->where('client_id', $v))
            ->when($filters['direccion'] ?? null, fn ($q, $v) => $q->where('direccion', $v))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($profile) => [
                'id' => $profile->id,
                'codigo' => $profile->codigo,
                'nombre' => $profile->nombre,
                'cliente' => $profile->client?->nombre,
                'direccion' => $profile->direccion,
                'estado' => $profile->estado?->value,
                'estado_label' => $profile->estado?->label(),
                'estado_color' => $profile->estado?->color(),
                'version' => $profile->currentVersion?->version,
                'tipo_salida' => $profile->tipo_salida,
                'activo' => $profile->activo,
                'creador' => $profile->createdBy?->name,
                'created_at' => $profile->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Integrations/Profiles/Index', [
            'profiles' => $profiles,
            'filters' => $filters,
            'clients' => IntegrationClient::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
            'statuses' => collect(IntegrationProfileStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', IntegrationProfile::class);

        return Inertia::render('Integrations/Profiles/Create', [
            'clients' => IntegrationClient::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
            'source_adapters' => collect(SourceAdapterFactory::available())->map(fn ($label, $key) => [
                'value' => $key, 'label' => $label,
            ])->values(),
            'exporters' => array_keys(OutputExporterFactory::available()),
        ]);
    }

    public function store(Request $request, IntegrationAuditService $audit)
    {
        $this->authorize('create', IntegrationProfile::class);

        $data = $request->validate([
            'client_id' => 'required|exists:integration_clients,id',
            'codigo' => 'required|string|max:50|unique:integration_profiles,codigo',
            'nombre' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'direccion' => 'required|in:entrada,salida',
            'source_adapter' => 'nullable|string|max:100',
            'exporter' => 'nullable|string|max:100',
            'tipo_salida' => 'required|string|max:50',
            'zona_horaria' => 'nullable|string|max:50',
            'error_config' => 'nullable|array',
            'idempotency_config' => 'nullable|array',
            'retencion_config' => 'nullable|array',
        ]);

        $profile = IntegrationProfile::create([
            ...$data,
            'estado' => IntegrationProfileStatus::BORRADOR,
            'activo' => true,
            'created_by' => $request->user()->id,
        ]);

        $version = IntegrationProfileVersion::create([
            'profile_id' => $profile->id,
            'version' => 1,
            'estado' => IntegrationProfileStatus::BORRADOR->value,
            'descripcion' => 'Versión inicial',
            'created_by' => $request->user()->id,
        ]);

        $profile->update(['current_version_id' => $version->id]);

        $audit->profileCreated($profile->id, $profile->nombre, $profile->codigo);

        return redirect()->route('integrations.profiles.edit', $profile)
            ->with('success', 'Perfil creado correctamente.');
    }

    public function show(IntegrationProfile $profile)
    {
        $this->authorize('view', $profile);

        $profile->load(['client', 'currentVersion', 'versions' => fn ($q) => $q->latest('version'), 'createdBy', 'updatedBy']);

        $lastRun = $profile->runs()->with('user')->latest()->first();

        return Inertia::render('Integrations/Profiles/Show', [
            'profile' => [
                'id' => $profile->id,
                'codigo' => $profile->codigo,
                'nombre' => $profile->nombre,
                'descripcion' => $profile->descripcion,
                'cliente' => $profile->client?->nombre,
                'direccion' => $profile->direccion,
                'estado' => $profile->estado?->value,
                'estado_label' => $profile->estado?->label(),
                'tipo_salida' => $profile->tipo_salida,
                'source_adapter' => $profile->source_adapter,
                'exporter' => $profile->exporter,
                'zona_horaria' => $profile->zona_horaria,
                'activo' => $profile->activo,
                'version_actual' => $profile->currentVersion?->version,
                'creador' => $profile->createdBy?->name,
                'created_at' => $profile->created_at?->format('Y-m-d H:i'),
                'versions_count' => $profile->versions->count(),
                'last_run' => $lastRun ? [
                    'id' => $lastRun->id,
                    'estado' => $lastRun->estado?->label(),
                    'usuario' => $lastRun->user?->name,
                    'total' => $lastRun->total_registros,
                    'created_at' => $lastRun->created_at?->format('Y-m-d H:i'),
                ] : null,
            ],
            'versions' => $profile->versions->map(fn ($v) => [
                'id' => $v->id,
                'version' => $v->version,
                'estado' => $v->estado,
                'inmutable' => $v->inmutable,
                'descripcion' => $v->descripcion,
                'published_at' => $v->published_at?->format('Y-m-d H:i'),
                'created_at' => $v->created_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function edit(IntegrationProfile $profile)
    {
        $this->authorize('update', $profile);

        if ($profile->estado?->value === IntegrationProfileStatus::PUBLICADO->value) {
            $profile = $this->createNewDraftVersion($profile);
        }

        $profile->load(['client', 'currentVersion' => fn ($q) => $q->with([
            'inputFields' => fn ($q) => $q->where('activo', true)->orderBy('posicion'),
            'outputFields' => fn ($q) => $q->where('activo', true)->orderBy('posicion'),
            'rules' => fn ($q) => $q->with(['inputs', 'outputs'])->where('activo', true)->orderBy('orden'),
        ])]);

        $version = $profile->currentVersion;

        return Inertia::render('Integrations/Profiles/Edit', [
            'profile' => [
                'id' => $profile->id,
                'client_id' => $profile->client_id,
                'codigo' => $profile->codigo,
                'nombre' => $profile->nombre,
                'descripcion' => $profile->descripcion,
                'direccion' => $profile->direccion,
                'estado' => $profile->estado?->value,
                'tipo_salida' => $profile->tipo_salida,
                'source_adapter' => $profile->source_adapter,
                'exporter' => $profile->exporter,
                'zona_horaria' => $profile->zona_horaria,
                'error_config' => $profile->error_config,
                'idempotency_config' => $profile->idempotency_config,
                'retencion_config' => $profile->retencion_config,
                'activo' => $profile->activo,
                'version_id' => $version->id,
                'version' => $version->version,
                'version_estado' => $version->estado,
                'inmutable' => $version->inmutable,
            ],
            'input_fields' => $version->inputFields->map(fn ($f) => [
                'id' => $f->id,
                'clave' => $f->clave,
                'etiqueta' => $f->etiqueta,
                'tipo_dato' => $f->tipo_dato?->value,
                'tipo_dato_label' => $f->tipo_dato?->label(),
                'obligatorio' => $f->obligatorio,
                'permite_nulo' => $f->permite_nulo,
                'valor_ejemplo' => $f->valor_ejemplo,
                'posicion' => $f->posicion,
                'activo' => $f->activo,
            ]),
            'output_fields' => $version->outputFields->map(fn ($f) => [
                'id' => $f->id,
                'clave_externa' => $f->clave_externa,
                'etiqueta' => $f->etiqueta,
                'tipo_dato' => $f->tipo_dato?->value,
                'tipo_dato_label' => $f->tipo_dato?->label(),
                'obligatorio' => $f->obligatorio,
                'permite_nulo' => $f->permite_nulo,
                'valor_defecto' => $f->valor_defecto,
                'posicion' => $f->posicion,
                'activo' => $f->activo,
            ]),
            'rules' => $version->rules->map(fn ($r) => [
                'id' => $r->id,
                'tipo' => $r->tipo?->value ?? $r->tipo,
                'tipo_label' => $r->tipo?->label(),
                'nombre' => $r->nombre,
                'orden' => $r->orden,
                'obligatoria' => $r->obligatoria,
                'politica_error' => $r->politica_error?->value,
                'activo' => $r->activo,
                'inputs' => $r->inputs->map(fn ($i) => ['clave_origen' => $i->clave_origen, 'alias' => $i->alias]),
                'outputs' => $r->outputs->map(fn ($o) => ['clave_destino' => $o->clave_destino]),
            ]),
            'clients' => IntegrationClient::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
            'field_types' => collect(\App\Enums\IntegrationFieldType::cases())->map(fn ($t) => [
                'value' => $t->value, 'label' => $t->label(),
            ]),
            'rule_types' => collect(\App\Enums\IntegrationRuleType::cases())->map(fn ($t) => [
                'value' => $t->value, 'label' => $t->label(),
            ]),
            'error_policies' => collect(\App\Enums\IntegrationRuleErrorPolicy::cases())->map(fn ($p) => [
                'value' => $p->value, 'label' => $p->label(),
            ]),
        ]);
    }

    public function update(Request $request, IntegrationProfile $profile, IntegrationAuditService $audit)
    {
        $this->authorize('update', $profile);

        if ($profile->estado?->value === IntegrationProfileStatus::PUBLICADO->value) {
            return back()->with('error', 'No se puede editar un perfil publicado. Cree una nueva versión.');
        }

        $data = $request->validate([
            'client_id' => 'required|exists:integration_clients,id',
            'codigo' => "required|string|max:50|unique:integration_profiles,codigo,{$profile->id}",
            'nombre' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'direccion' => 'required|in:entrada,salida',
            'source_adapter' => 'nullable|string|max:100',
            'exporter' => 'nullable|string|max:100',
            'tipo_salida' => 'required|string|max:50',
            'zona_horaria' => 'nullable|string|max:50',
            'error_config' => 'nullable|array',
            'idempotency_config' => 'nullable|array',
            'retencion_config' => 'nullable|array',
        ]);

        $old = $profile->only(['codigo', 'nombre', 'direccion']);

        $profile->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        $audit->profileUpdated($profile->id, $profile->nombre, $old, $data);

        return redirect()->route('integrations.profiles.edit', $profile)
            ->with('success', 'Perfil actualizado correctamente.');
    }

    public function duplicate(Request $request, IntegrationProfile $profile, IntegrationAuditService $audit)
    {
        $this->authorize('duplicate', $profile);

        $newProfile = $profile->replicate();
        $newProfile->codigo = $profile->codigo . '-copy';
        $newProfile->nombre = $profile->nombre . ' (copia)';
        $newProfile->estado = IntegrationProfileStatus::BORRADOR;
        $newProfile->current_version_id = null;
        $newProfile->activo = true;
        $newProfile->created_by = $request->user()->id;
        $newProfile->save();

        if ($profile->currentVersion) {
            $oldVersion = $profile->currentVersion;
            $newVersion = $oldVersion->replicate();
            $newVersion->profile_id = $newProfile->id;
            $newVersion->version = 1;
            $newVersion->estado = IntegrationProfileStatus::BORRADOR->value;
            $newVersion->inmutable = false;
            $newVersion->published_at = null;
            $newVersion->published_by = null;
            $newVersion->created_by = $request->user()->id;
            $newVersion->save();

            $newProfile->update(['current_version_id' => $newVersion->id]);
        }

        $audit->profileDuplicated($profile->id, $profile->nombre, $newProfile->id);
        $audit->profileCreated($newProfile->id, $newProfile->nombre, $newProfile->codigo);

        return redirect()->route('integrations.profiles.edit', $newProfile)
            ->with('success', 'Perfil duplicado correctamente.');
    }

    public function publish(Request $request, IntegrationProfile $profile, IntegrationAuditService $audit)
    {
        $this->authorize('publish', $profile);

        $version = $profile->currentVersion;

        if (!$version) {
            return back()->with('error', 'El perfil no tiene una versión para publicar.');
        }

        $validationErrors = app(\App\Services\Integrations\Engine\TransformationEngine::class)
            ->validateProfile($version);

        $hasErrors = collect($validationErrors)->contains(fn ($e) => $e['type'] === 'error');

        if ($hasErrors) {
            return back()->with('error', 'El perfil tiene errores de validación. Revise las reglas.');
        }

        $version->update([
            'estado' => IntegrationProfileStatus::PUBLICADO->value,
            'inmutable' => true,
            'published_at' => now(),
            'published_by' => $request->user()->id,
        ]);

        $profile->update([
            'estado' => IntegrationProfileStatus::PUBLICADO,
            'updated_by' => $request->user()->id,
        ]);

        $audit->profilePublished($profile->id, $profile->nombre, $version->version);

        return redirect()->route('integrations.profiles.show', $profile)
            ->with('success', 'Perfil publicado correctamente.');
    }

    public function toggleActive(Request $request, IntegrationProfile $profile, IntegrationAuditService $audit)
    {
        $this->authorize('update', $profile);

        $oldStatus = $profile->estado?->label();
        $profile->update([
            'activo' => !$profile->activo,
            'updated_by' => $request->user()->id,
        ]);
        $audit->profileStatusChanged($profile->id, $profile->nombre, $oldStatus, $profile->activo ? 'Activo' : 'Inactivo');

        return back()->with('success', $profile->activo ? 'Perfil activado.' : 'Perfil desactivado.');
    }

    private function createNewDraftVersion(IntegrationProfile $profile): IntegrationProfile
    {
        $currentVersion = $profile->currentVersion;

        if (!$currentVersion) {
            return $profile;
        }

        $newVersionNum = $profile->versions()->max('version') + 1;

        $newVersion = IntegrationProfileVersion::create([
            'profile_id' => $profile->id,
            'version' => $newVersionNum,
            'estado' => IntegrationProfileStatus::BORRADOR->value,
            'inmutable' => false,
            'descripcion' => "Nueva versión a partir de v{$currentVersion->version}",
            'snapshot_config' => $currentVersion->snapshot_config,
            'created_by' => auth()->id(),
        ]);

        foreach ($currentVersion->inputFields as $field) {
            $newField = $field->replicate();
            $newField->profile_version_id = $newVersion->id;
            $newField->save();
        }

        foreach ($currentVersion->outputFields as $field) {
            $newField = $field->replicate();
            $newField->profile_version_id = $newVersion->id;
            $newField->save();
        }

        foreach ($currentVersion->rules as $rule) {
            $newRule = $rule->replicate();
            $newRule->profile_version_id = $newVersion->id;
            $newRule->save();
        }

        $profile->update(['current_version_id' => $newVersion->id]);

        return $profile->fresh();
    }
}
