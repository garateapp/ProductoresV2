<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Contractor;
use App\Models\Crew;
use App\Models\Credential;
use App\Models\Field;
use App\Models\FruitConfig;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerCredentialLink;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FieldManagementController extends Controller
{
    private function ensureCanManage(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole(['Administrador', 'Agronomo'])) {
            abort(403);
        }
    }

    public function index(Request $request): Response
    {
        $this->ensureCanManage();

        $contractors = Contractor::orderBy('name')
            ->get()
            ->map(fn (Contractor $contractor) => [
                'id' => $contractor->id,
                'name' => $contractor->name,
            ]);

        $crews = Crew::with('contractor')
            ->orderBy('name')
            ->get()
            ->map(fn (Crew $crew) => [
                'id' => $crew->id,
                'name' => $crew->name,
                'contractor' => $crew->contractor ? [
                    'id' => $crew->contractor->id,
                    'name' => $crew->contractor->name,
                ] : null,
            ]);

        $workerSearch = trim((string) $request->input('worker_search', ''));
        $workersQuery = Worker::with(['contractor', 'crew', 'activeCredentialLink.credential'])
            ->orderBy('full_name');

        if ($workerSearch !== '') {
            $workersQuery->where(function ($q) use ($workerSearch) {
                $q->where('full_name', 'like', "%{$workerSearch}%")
                    ->orWhere('national_id', 'like', "%{$workerSearch}%");
            });
        }

        $workers = $workersQuery->paginate(10)->withQueryString();
        $workers->getCollection()->transform(function (Worker $worker) {
            return [
                'id' => $worker->id,
                'national_id' => $worker->national_id,
                'full_name' => $worker->full_name,
                'role' => $worker->role,
                'status' => $worker->status,
                'contractor' => $worker->contractor ? [
                    'id' => $worker->contractor->id,
                    'name' => $worker->contractor->name,
                ] : null,
                'crew' => $worker->crew ? [
                    'id' => $worker->crew->id,
                    'name' => $worker->crew->name,
                ] : null,
                'credential' => ($worker->activeCredentialLink && $worker->activeCredentialLink->credential)
                    ? [
                        'id' => $worker->activeCredentialLink->credential->id,
                        'qr_uid' => $worker->activeCredentialLink->credential->qr_uid,
                    ]
                    : null,
            ];
        });

        $workerOptions = Worker::orderBy('full_name')
            ->get(['id', 'full_name'])
            ->map(fn (Worker $worker) => [
                'id' => $worker->id,
                'full_name' => $worker->full_name,
            ]);

        $credentials = Credential::with(['activeLink.worker'])
            ->orderBy('qr_uid')
            ->get()
            ->map(fn (Credential $credential) => [
                'id' => $credential->id,
                'qr_uid' => $credential->qr_uid,
                'status' => $credential->status,
                'assigned_worker' => $credential->activeLink && $credential->activeLink->worker
                    ? [
                        'id' => $credential->activeLink->worker->id,
                        'full_name' => $credential->activeLink->worker->full_name,
                    ]
                    : null,
            ]);

        $fields = Field::with(['producer', 'blocks'])
            ->orderBy('name')
            ->get()
            ->map(fn (Field $field) => [
                'id' => $field->id,
                'name' => $field->name,
                'producer' => $field->producer ? [
                    'id' => $field->producer->id,
                    'name' => $field->producer->name,
                ] : null,
                'blocks_count' => $field->blocks->count(),
            ]);

        $blocks = Block::with('field.producer')
            ->orderBy('name')
            ->get()
            ->map(fn (Block $block) => [
                'id' => $block->id,
                'name' => $block->name,
                'field' => $block->field ? [
                    'id' => $block->field->id,
                    'name' => $block->field->name,
                    'producer' => $block->field->producer ? [
                        'id' => $block->field->producer->id,
                        'name' => $block->field->producer->name,
                    ] : null,
                ] : null,
            ]);

        $fruitConfigs = FruitConfig::orderBy('species')
            ->orderBy('variety')
            ->get()
            ->map(fn (FruitConfig $config) => [
                'id' => $config->id,
                'species' => $config->species,
                'variety' => $config->variety,
                'tottes_per_bin' => $config->tottes_per_bin,
                'status' => $config->status,
            ]);

        $producerOptions = User::role('Productor')
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $producer) => [
                'id' => $producer->id,
                'name' => $producer->name,
            ]);

        $especies = \App\Models\Especie::orderBy('name')->get(['id', 'name']);
        $variedades = \App\Models\Variedad::orderBy('name')->get(['id', 'name', 'especie_id']);

        return Inertia::render('FieldManagement/Index', [
            'contractors' => $contractors,
            'crews' => $crews,
            'workers' => $workers,
            'credentials' => $credentials,
            'fields' => $fields,
            'blocks' => $blocks,
            'fruitConfigs' => $fruitConfigs,
            'producerOptions' => $producerOptions,
            'workerOptions' => $workerOptions,
            'especies' => $especies,
            'variedades' => $variedades,
            'filters' => [
                'worker_search' => $workerSearch,
            ],
        ]);
    }

    public function storeContractor(Request $request): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Contractor::create($data);

        return back()->with('success', 'Contratista creado correctamente.');
    }

    public function updateContractor(Request $request, Contractor $contractor): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $contractor->update($data);

        return back()->with('success', 'Contratista actualizado.');
    }

    public function destroyContractor(Contractor $contractor): RedirectResponse
    {
        $this->ensureCanManage();
        $contractor->delete();

        return back()->with('success', 'Contratista eliminado.');
    }

    public function storeCrew(Request $request): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contractor_id' => ['required', 'exists:contractors,id'],
        ]);

        Crew::create($data);

        return back()->with('success', 'Cuadrilla creada correctamente.');
    }

    public function updateCrew(Request $request, Crew $crew): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contractor_id' => ['required', 'exists:contractors,id'],
        ]);

        $crew->update($data);

        return back()->with('success', 'Cuadrilla actualizada.');
    }

    public function destroyCrew(Crew $crew): RedirectResponse
    {
        $this->ensureCanManage();
        $crew->delete();

        return back()->with('success', 'Cuadrilla eliminada.');
    }

    public function storeWorker(Request $request): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'national_id' => ['nullable', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:active,inactive'],
            'contractor_id' => ['nullable', 'exists:contractors,id'],
            'crew_id' => ['nullable', 'exists:crews,id'],
        ]);

        Worker::create($data);

        return back()->with('success', 'Trabajador creado.');
    }

    public function updateWorker(Request $request, Worker $worker): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'national_id' => ['nullable', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:active,inactive'],
            'contractor_id' => ['nullable', 'exists:contractors,id'],
            'crew_id' => ['nullable', 'exists:crews,id'],
        ]);

        $worker->update($data);

        return back()->with('success', 'Trabajador actualizado.');
    }

    public function destroyWorker(Worker $worker): RedirectResponse
    {
        $this->ensureCanManage();
        $worker->delete();

        return back()->with('success', 'Trabajador eliminado.');
    }

    public function storeCredential(Request $request): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'qr_uid' => ['required', 'string', 'max:255', 'unique:credentials,qr_uid'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        Credential::create($data);

        return back()->with('success', 'Credencial creada.');
    }

    public function updateCredential(Request $request, Credential $credential): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'qr_uid' => ['required', 'string', 'max:255', 'unique:credentials,qr_uid,'.$credential->id],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $credential->update($data);

        return back()->with('success', 'Credencial actualizada.');
    }

    public function destroyCredential(Credential $credential): RedirectResponse
    {
        $this->ensureCanManage();
        $credential->delete();

        return back()->with('success', 'Credencial eliminada.');
    }

    public function assignCredential(Request $request): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'worker_id' => ['required', 'exists:workers,id'],
            'credential_id' => ['required', 'exists:credentials,id'],
        ]);

        $now = now();

        DB::transaction(function () use ($data, $now) {
            $previousWorkerCredentialIds = WorkerCredentialLink::where('worker_id', $data['worker_id'])
                ->whereNull('unassigned_at')
                ->pluck('credential_id')
                ->all();

            WorkerCredentialLink::where('worker_id', $data['worker_id'])
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => $now]);

            WorkerCredentialLink::where('credential_id', $data['credential_id'])
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => $now]);

            if (! empty($previousWorkerCredentialIds)) {
                Credential::whereIn('id', $previousWorkerCredentialIds)->update(['status' => 'available']);
            }

            WorkerCredentialLink::create([
                'worker_id' => $data['worker_id'],
                'credential_id' => $data['credential_id'],
                'assigned_at' => $now,
                'assigned_by_user_id' => Auth::id(),
                'unassigned_at' => null,
            ]);

            Credential::where('id', $data['credential_id'])->update(['status' => 'assigned']);
        });

        return back()->with('success', 'Credencial asignada al trabajador.');
    }

    public function storeField(Request $request): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'producer_id' => ['nullable', 'exists:users,id'],
        ]);

        Field::create($data);

        return back()->with('success', 'Campo creado.');
    }

    public function updateField(Request $request, Field $field): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'producer_id' => ['nullable', 'exists:users,id'],
        ]);

        $field->update($data);

        return back()->with('success', 'Campo actualizado.');
    }

    public function destroyField(Field $field): RedirectResponse
    {
        $this->ensureCanManage();
        $field->delete();

        return back()->with('success', 'Campo eliminado.');
    }

    public function storeBlock(Request $request): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'field_id' => ['required', 'exists:fields,id'],
        ]);

        Block::create($data);

        return back()->with('success', 'Cuartel creado.');
    }

    public function updateBlock(Request $request, Block $block): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'field_id' => ['required', 'exists:fields,id'],
        ]);

        $block->update($data);

        return back()->with('success', 'Cuartel actualizado.');
    }

    public function destroyBlock(Block $block): RedirectResponse
    {
        $this->ensureCanManage();
        $block->delete();

        return back()->with('success', 'Cuartel eliminado.');
    }

    public function storeFruitConfig(Request $request): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'species' => ['required', 'string', 'max:255'],
            'variety' => ['nullable', 'string', 'max:255'],
            'tottes_per_bin' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        FruitConfig::create($data);

        return back()->with('success', 'Configuracion de fruta creada.');
    }

    public function updateFruitConfig(Request $request, FruitConfig $fruitConfig): RedirectResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'species' => ['required', 'string', 'max:255'],
            'variety' => ['nullable', 'string', 'max:255'],
            'tottes_per_bin' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $fruitConfig->update($data);

        return back()->with('success', 'Configuracion de fruta actualizada.');
    }

    public function destroyFruitConfig(FruitConfig $fruitConfig): RedirectResponse
    {
        $this->ensureCanManage();
        $fruitConfig->delete();

        return back()->with('success', 'Configuracion de fruta eliminada.');
    }

    /**
     * API: entrega datos completos para sincronizar con la app móvil.
     */
    public function apiSync(Request $request): JsonResponse
    {
        $this->ensureCanManage();

        $sinceInput = $request->query('since');
        $since = null;

        if ($sinceInput !== null && $sinceInput !== '') {
            try {
                $since = Carbon::parse($sinceInput);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Parámetro "since" inválido. Use formato ISO 8601 (ej: 2024-12-31T23:59:59Z).',
                ], 422);
            }
        }

        $contractors = Contractor::orderBy('name')
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->get()
            ->map(fn (Contractor $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'updated_at' => $c->updated_at?->toISOString(),
            ]);

        $crews = Crew::with('contractor')
            ->orderBy('name')
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->get()
            ->map(fn (Crew $crew) => [
                'id' => $crew->id,
                'name' => $crew->name,
                'contractor' => $crew->contractor ? [
                    'id' => $crew->contractor->id,
                    'name' => $crew->contractor->name,
                ] : null,
                'updated_at' => $crew->updated_at?->toISOString(),
            ]);

        $workers = Worker::with(['contractor', 'crew', 'activeCredentialLink.credential'])
            ->orderBy('full_name')
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->get()
            ->map(fn (Worker $worker) => [
                'id' => $worker->id,
                'national_id' => $worker->national_id,
                'full_name' => $worker->full_name,
                'role' => $worker->role,
                'status' => $worker->status,
                'contractor' => $worker->contractor ? [
                    'id' => $worker->contractor->id,
                    'name' => $worker->contractor->name,
                ] : null,
                'crew' => $worker->crew ? [
                    'id' => $worker->crew->id,
                    'name' => $worker->crew->name,
                ] : null,
                'credential' => ($worker->activeCredentialLink && $worker->activeCredentialLink->credential)
                    ? [
                        'id' => $worker->activeCredentialLink->credential->id,
                        'qr_uid' => $worker->activeCredentialLink->credential->qr_uid,
                    ]
                    : null,
                'updated_at' => $worker->updated_at?->toISOString(),
            ]);

        $credentials = Credential::with(['activeLink.worker'])
            ->orderBy('qr_uid')
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->get()
            ->map(fn (Credential $credential) => [
                'id' => $credential->id,
                'qr_uid' => $credential->qr_uid,
                'status' => $credential->status,
                'assigned_worker' => $credential->activeLink && $credential->activeLink->worker
                    ? [
                        'id' => $credential->activeLink->worker->id,
                        'full_name' => $credential->activeLink->worker->full_name,
                    ]
                    : null,
                'updated_at' => $credential->updated_at?->toISOString(),
            ]);

        $fields = Field::with(['producer'])
            ->orderBy('name')
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->get()
            ->map(fn (Field $field) => [
                'id' => $field->id,
                'name' => $field->name,
                'producer' => $field->producer ? [
                    'id' => $field->producer->id,
                    'name' => $field->producer->name,
                ] : null,
                'updated_at' => $field->updated_at?->toISOString(),
            ]);

        $blocks = Block::with('field.producer')
            ->orderBy('name')
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->get()
            ->map(fn (Block $block) => [
                'id' => $block->id,
                'name' => $block->name,
                'field' => $block->field ? [
                    'id' => $block->field->id,
                    'name' => $block->field->name,
                    'producer' => $block->field->producer ? [
                        'id' => $block->field->producer->id,
                        'name' => $block->field->producer->name,
                    ] : null,
                ] : null,
                'updated_at' => $block->updated_at?->toISOString(),
            ]);

        $fruitConfigs = FruitConfig::orderBy('species')
            ->orderBy('variety')
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->get()
            ->map(fn (FruitConfig $config) => [
                'id' => $config->id,
                'species' => $config->species,
                'variety' => $config->variety,
                'tottes_per_bin' => $config->tottes_per_bin,
                'status' => $config->status,
                'updated_at' => $config->updated_at?->toISOString(),
            ]);

        $especies = \App\Models\Especie::orderBy('name')
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->get()
            ->map(fn ($especie) => [
                'id' => $especie->id,
                'name' => $especie->name,
                'updated_at' => $especie->updated_at?->toISOString(),
            ]);

        $variedades = \App\Models\Variedad::orderBy('name')
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->get()
            ->map(fn ($variedad) => [
                'id' => $variedad->id,
                'name' => $variedad->name,
                'especie_id' => $variedad->especie_id,
                'updated_at' => $variedad->updated_at?->toISOString(),
            ]);

        return response()->json([
            'contractors' => $contractors,
            'crews' => $crews,
            'workers' => $workers,
            'credentials' => $credentials,
            'fields' => $fields,
            'blocks' => $blocks,
            'fruit_configs' => $fruitConfigs,
            'especies' => $especies,
            'variedades' => $variedades,
        ]);
    }
}
