<?php

namespace App\Http\Controllers;

use App\Models\ProspectoProductor;
use App\Models\Especie;
use App\Models\Service;
use App\Models\User;
use App\Mail\ProspectoProductorCreated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProspectoProductorController extends Controller
{
    public function create(Request $request): Response
    {
        $this->ensureAdmin($request);

        $especies = Especie::orderBy('name')->get(['id', 'name']);
        $services = Service::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/ProspectosProductores/Create', [
            'especies' => $especies,
            'services' => $services,
        ]);
    }

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $prospectos = ProspectoProductor::query()
            ->latest()
            ->paginate(15)
            ->through(function (ProspectoProductor $prospecto) {
                return [
                    'id' => $prospecto->id,
                    'razon_social' => $prospecto->razon_social,
                    'rut' => $prospecto->rut,
                    'email' => $prospecto->email,
                    'ggn' => $prospecto->ggn,
                    'created_at' => optional($prospecto->created_at)->format('Y-m-d H:i:s'),
                    'validated_at' => optional($prospecto->validated_at)->format('Y-m-d H:i:s'),
                    'producer_id' => $prospecto->producer_id,
                ];
            });

        return Inertia::render('Admin/ProspectosProductores/Index', [
            'prospectos' => $prospectos,
        ]);
    }

    public function edit(Request $request, ProspectoProductor $prospecto): Response
    {
        $this->ensureAdmin($request);

        $especies = Especie::orderBy('name')->get(['id', 'name']);
        $services = Service::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/ProspectosProductores/Edit', [
            'prospecto' => $prospecto,
            'especies' => $especies,
            'services' => $services,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $validated = $this->validateProspectoRequest($request);

        $validated['predios'] = $this->filterEmptyRows($validated['predios'] ?? []);
        $validated['produccion'] = $this->filterEmptyRows($validated['produccion'] ?? []);
        $validated['created_by'] = $request->user()?->id;

        $prospecto = ProspectoProductor::create($validated);

        $recipients = array_values(array_filter(
            config('reports.prospecto_productor_recipients', [])
        ));

        if (! empty($recipients)) {
            Mail::to($recipients)->send(new ProspectoProductorCreated($prospecto));
        }

        return redirect()
            ->route('prospectos-productores.edit', $prospecto)
            ->with('success', 'Prospecto guardado correctamente.');
    }

    public function update(Request $request, ProspectoProductor $prospecto): RedirectResponse
    {
        $this->ensureAdmin($request);

        $validated = $this->validateProspectoRequest($request);
        $validated['predios'] = $this->filterEmptyRows($validated['predios'] ?? []);
        $validated['produccion'] = $this->filterEmptyRows($validated['produccion'] ?? []);

        $prospecto->update($validated);

        return redirect()
            ->route('prospectos-productores.edit', $prospecto)
            ->with('success', 'Prospecto actualizado correctamente.');
    }

    public function validateProspecto(Request $request, ProspectoProductor $prospecto): RedirectResponse
    {
        $this->ensureAdmin($request);

        $prospecto->validated_at = now();
        $prospecto->validated_by = $request->user()?->id;
        $prospecto->save();

        return redirect()
            ->route('prospectos-productores.edit', $prospecto)
            ->with('success', 'Prospecto validado correctamente.');
    }

    public function createProducer(Request $request, ProspectoProductor $prospecto): RedirectResponse
    {
        $this->ensureAdmin($request);

        if (! $prospecto->validated_at) {
            return redirect()
                ->route('prospectos-productores.edit', $prospecto)
                ->with('error', 'Debes validar el prospecto antes de crear el productor.');
        }

        if ($prospecto->producer_id) {
            return redirect()
                ->route('prospectos-productores.edit', $prospecto)
                ->with('success', 'El productor ya fue creado para este prospecto.');
        }

        if (! $prospecto->razon_social || ! $prospecto->email) {
            return redirect()
                ->route('prospectos-productores.edit', $prospecto)
                ->with('error', 'Completa Razón Social y Email antes de crear el productor.');
        }

        if (User::where('email', $prospecto->email)->exists()) {
            return redirect()
                ->route('prospectos-productores.edit', $prospecto)
                ->with('error', 'El email ya existe en el portal. Revisa el prospecto.');
        }

        $password = Str::random(12);

        $producer = User::create([
            'name' => $prospecto->razon_social,
            'email' => $prospecto->email,
            'password' => Hash::make($password),
            'rut' => $prospecto->rut,
            'predio' => $prospecto->direccion_predio,
            'comuna' => $prospecto->comuna_comercial ?: $prospecto->comuna_predio,
            'direccion' => $prospecto->direccion_comercial,
            'emnotification' => true,
            'is_active' => true,
        ]);

        $producer->assignRole('Productor');

        $prospecto->producer_id = $producer->id;
        $prospecto->save();

        if ($request->boolean('flow_redirect')) {
            return redirect()
                ->route('contracts.producer-flow', [
                    'rut' => $prospecto->rut,
                    'user_id' => $producer->id,
                ])
                ->with('success', 'Productor creado correctamente desde el prospecto.');
        }

        return redirect()
            ->route('producers.edit', $producer)
            ->with('success', 'Productor creado correctamente desde el prospecto.');
    }

    public function lookupByRut(Request $request, string $rut): JsonResponse
    {
        $this->ensureAdmin($request);

        $normalized = strtoupper(preg_replace('/[^0-9Kk]/', '', $rut));

        if ($normalized === '') {
            return response()->json([
                'found' => false,
                'message' => 'RUT inválido.',
            ]);
        }

        $record = DB::connection('sqlsrv')
            ->table('V_ADM_Entidades')
            ->select([
                'id',
                'codigo',
                'rut',
                'nombre',
                'direccion',
                'n_comuna',
                'n_region',
                'codigo_sag',
                'tipo',
            ])
            ->where('tipo', 'Productor')
            ->whereRaw("REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '') = ?", [$normalized])
            ->orderByDesc('id')
            ->first();

        if (! $record) {
            return response()->json([
                'found' => false,
                'message' => 'No se encontró productor en SQLSRV.',
            ]);
        }

        return response()->json([
            'found' => true,
            'data' => [
                'razon_social' => $record->nombre,
                'direccion_predio' => $record->direccion,
                'direccion_comercial' => $record->direccion,
                'comuna_predio' => $record->n_comuna,
                'comuna_comercial' => $record->n_comuna,
                'codigo_sag' => $record->codigo_sag,
                'codigo' => $record->codigo,
            ],
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole(['Admin', 'Administrador', 'Contrato']);

        abort_unless($isAdmin, 403);
    }

    private function filterEmptyRows(array $rows): array
    {
        return array_values(array_filter($rows, function (array $row) {
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    return true;
                }
            }

            return false;
        }));
    }

    private function validateProspectoRequest(Request $request): array
    {
        return $request->validate([
            'razon_social' => ['nullable', 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:60'],
            'ggn' => ['nullable', 'string', 'max:120'],
            'tipo_empresa' => ['nullable', 'string', 'max:60'],
            'giro' => ['nullable', 'string', 'max:255'],
            'direccion_comercial' => ['nullable', 'string', 'max:255'],
            'comuna_comercial' => ['nullable', 'string', 'max:120'],
            'fono' => ['nullable', 'string', 'max:60'],
            'fax_comercial' => ['nullable', 'string', 'max:60'],
            'direccion_predio' => ['nullable', 'string', 'max:255'],
            'comuna_predio' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'fax_contacto' => ['nullable', 'string', 'max:60'],
            'nombre_rep_legal' => ['nullable', 'string', 'max:255'],
            'rut_rep_legal' => ['nullable', 'string', 'max:60'],
            'direccion_rep_legal' => ['nullable', 'string', 'max:255'],
            'banco' => ['nullable', 'string', 'max:120'],
            'nombre_titular' => ['nullable', 'string', 'max:255'],
            'cuenta_corriente' => ['nullable', 'string', 'max:120'],
            'moneda' => ['nullable', 'string', 'max:30'],
            'sucursal' => ['nullable', 'string', 'max:120'],
            'constitucion_fecha_escritura' => ['nullable', 'date'],
            'notario_publico' => ['nullable', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'predios' => ['nullable', 'array'],
            'predios.*.nombre_predio' => ['nullable', 'string', 'max:255'],
            'predios.*.comuna' => ['nullable', 'string', 'max:120'],
            'predios.*.predio' => ['nullable', 'string', 'max:120'],
            'predios.*.forma_dominio' => ['nullable', 'string', 'max:120'],
            'predios.*.rol_avaluo' => ['nullable', 'string', 'max:120'],
            'predios.*.fojas' => ['nullable', 'string', 'max:120'],
            'predios.*.numero' => ['nullable', 'string', 'max:120'],
            'predios.*.ano' => ['nullable', 'string', 'max:10'],
            'predios.*.cbr' => ['nullable', 'string', 'max:120'],
            'predios.*.ciudad' => ['nullable', 'string', 'max:120'],
            'predios.*.comuna_cbr' => ['nullable', 'string', 'max:120'],
            'produccion' => ['nullable', 'array'],
            'produccion.*.especie_id' => ['nullable', 'integer', 'exists:especies,id'],
            'produccion.*.variedad_id' => ['nullable', 'integer', 'exists:variedads,id'],
            'produccion.*.kilos_totales' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
