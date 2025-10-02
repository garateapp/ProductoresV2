<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class ProducerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::role('Productor');

        // Active filter (default: only active)
        $showInactive = $request->boolean('show_inactive', false);
        if (! $showInactive) {
            $query->where('is_active', true);
        }

        // Filtering
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%')
                    ->orWhere('rut', 'like', '%'.$request->search.'%');
            });
        }

        // Sorting
        if ($request->has('sort_by') && $request->has('sort_order')) {
            $query->orderBy($request->sort_by, $request->sort_order);
        } else {
            $query->orderBy('name', 'asc'); // Default sort
        }

        // Pagination
        $producers = $query->paginate(10); // You can adjust the per-page value

        return Inertia::render('Producers/Index', [
            'producers' => $producers->through(function ($producer) {
                return [
                    'id' => $producer->id,
                    'name' => $producer->name,
                    'email' => $producer->email,
                    'rut' => $producer->rut,
                    'user' => $producer->user,
                    'idprod' => $producer->idprod,
                    'csg' => $producer->csg,
                    'emnotification' => $producer->emnotification,
                    'is_active' => (bool) $producer->is_active,
                    'kilos_netos' => $producer->kilos_netos,
                    'comercial' => $producer->comercial,
                    'desecho' => $producer->desecho,
                    'merma' => $producer->merma,
                    'exp' => $producer->exp,
                    'predio' => $producer->predio,
                    'comuna' => $producer->comuna,
                    'provincia' => $producer->provincia,
                    'direccion' => $producer->direccion,
                    'antiguedad' => $producer->antiguedad,
                    'fitosanitario' => $producer->fitosanitario,
                    'certificaciones' => $producer->certificaciones,
                    'status' => $producer->status,
                    'enviomasivo' => $producer->enviomasivo,
                ];
            }),
            'filters' => $request->all(['search', 'sort_by', 'sort_order', 'show_inactive']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Producers/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'rut' => 'nullable|string|max:255',
            'user' => 'nullable|string|max:255',
            'idprod' => 'nullable|string|max:255',
            'csg' => 'nullable|string|max:255',
            'emnotification' => 'boolean',
            'is_active' => 'boolean',
            'kilos_netos' => 'nullable|integer',
            'comercial' => 'nullable|integer',
            'desecho' => 'nullable|integer',
            'merma' => 'nullable|integer',
            'exp' => 'nullable|integer',
            'predio' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'provincia' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'antiguedad' => 'nullable|integer',
            'fitosanitario' => 'nullable|string|max:255',
            'certificaciones' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'enviomasivo' => 'boolean',
        ]);

        $producer = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rut' => $request->rut,
            'user' => $request->user,
            'idprod' => $request->idprod,
            'csg' => $request->csg,
            'emnotification' => $request->emnotification,
            'is_active' => $request->boolean('is_active', true),
            'kilos_netos' => $request->kilos_netos,
            'comercial' => $request->comercial,
            'desecho' => $request->desecho,
            'merma' => $request->merma,
            'exp' => $request->exp,
            'predio' => $request->predio,
            'comuna' => $request->comuna,
            'provincia' => $request->provincia,
            'direccion' => $request->direccion,
            'antiguedad' => $request->antiguedad,
            'fitosanitario' => $request->fitosanitario,
            'certificaciones' => $request->certificaciones,
            'status' => $request->status,
            'enviomasivo' => $request->enviomasivo,
        ]);

        $producer->assignRole('Productor');

        return redirect()->route('producers.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $producer)
    {
        $producer->load('agronomists');

        return Inertia::render('Producers/Edit', [
            'producer' => [
                'id' => $producer->id,
                'name' => $producer->name,
                'email' => $producer->email,
                'rut' => $producer->rut,
                'user' => $producer->user,
                'idprod' => $producer->idprod,
                'csg' => $producer->csg,
                'emnotification' => $producer->emnotification,
                'is_active' => (bool) $producer->is_active,
                'kilos_netos' => $producer->kilos_netos,
                'comercial' => $producer->comercial,
                'desecho' => $producer->desecho,
                'merma' => $producer->merma,
                'exp' => $producer->exp,
                'predio' => $producer->predio,
                'comuna' => $producer->comuna,
                'provincia' => $producer->provincia,
                'direccion' => $producer->direccion,
                'antiguedad' => $producer->antiguedad,
                'fitosanitario' => $producer->fitosanitario,
                'certificaciones' => $producer->certificaciones,
                'status' => $producer->status,
                'enviomasivo' => $producer->enviomasivo,
                'telefonos' => $producer->telefonos()->get(),
                'agronomists' => $producer->agronomists->map(function ($agronomist) {
                    return [
                        'id' => $agronomist->id,
                        'name' => $agronomist->name,
                        'email' => $agronomist->email,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $producer)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$producer->id,
            'rut' => 'nullable|string|max:255',
            'user' => 'nullable|string|max:255',
            'idprod' => 'nullable|string|max:255',
            'csg' => 'nullable|string|max:255',
            'emnotification' => 'boolean',
            'is_active' => 'boolean',
            'kilos_netos' => 'nullable|integer',
            'comercial' => 'nullable|integer',
            'desecho' => 'nullable|integer',
            'merma' => 'nullable|integer',
            'exp' => 'nullable|integer',
            'predio' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'provincia' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'antiguedad' => 'nullable|integer',
            'fitosanitario' => 'nullable|string|max:255',
            'certificaciones' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'enviomasivo' => 'boolean',
        ]);

        $producer->update([
            'name' => $request->name,
            'email' => $request->email,
            'rut' => $request->rut,
            'user' => $request->user,
            'idprod' => $request->idprod,
            'csg' => $request->csg,
            'emnotification' => $request->boolean('emnotification'),
            'is_active' => $request->boolean('is_active', $producer->is_active),
            'kilos_netos' => $request->kilos_netos,
            'comercial' => $request->comercial,
            'desecho' => $request->desecho,
            'merma' => $request->merma,
            'exp' => $request->exp,
            'predio' => $request->predio,
            'comuna' => $request->comuna,
            'provincia' => $request->provincia,
            'direccion' => $request->direccion,
            'antiguedad' => $request->antiguedad,
            'fitosanitario' => $request->fitosanitario,
            'certificaciones' => $request->certificaciones,
            'status' => $request->status,
            'enviomasivo' => $request->boolean('enviomasivo'),
        ]);

        return redirect()->route('producers.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $producer)
    {
        $producer->delete();

        return redirect()->route('producers.index');
    }

    public function syncActive(Request $request)
    {
        $dryRun = $request->boolean('dry_run', false);
        $params = $dryRun ? ['--dry-run' => true] : [];
        Artisan::call('producers:sync-active', $params);
        $output = Artisan::output();

        return redirect()->route('producers.index')
            ->with('success', 'Sincronización de estados ejecutada'.($dryRun ? ' (prueba)' : '').'.')
            ->with('sync_output', $output);
    }

    public function dashboard(User $producer)
    {
        $producer->load(['services']);
        $code = $producer->idprod;
        $name = $producer->name;

        $recepciones = \App\Models\Recepcion::query()
            ->when($code, fn($q) => $q->where('id_emisor', $code))
            ->orderByDesc('fecha_g_recepcion')
            ->limit(50)
            ->get(['id','numero_g_recepcion','fecha_g_recepcion','n_especie','n_variedad','cantidad','peso_neto','informe']);

        $procesos = \App\Models\Proceso::query()
            ->when($name, fn($q) => $q->where('agricola', $name))
            ->orderByDesc('fecha')
            ->limit(50)
            ->get(['id','n_proceso','fecha','especie','variedad','kilos_netos','informe','exp','comercial','merma']);

        $certifications = \App\Models\ProducerCertification::with(['certifyingHouse','certificateType','especie'])
            ->where('user_id', $producer->id)
            ->orderByDesc('expiration_date')
            ->limit(100)
            ->get();

        $markets = \App\Models\CsgEspecieCountryStatus::with(['especie:id,name','country:id,name'])
            ->where('user_id', $producer->id)
            ->limit(200)
            ->get();

        $contracts = \App\Models\Contract::where('user_id', $producer->id)
            ->orderByDesc('fecha_contrato')
            ->get(['id','user_id','contract_file_path','fecha_contrato','vencimiento','comision']);

        $recepBySpecies = collect();
        $procStackBySpecies = collect();
        $recepWeeklyBySpecies = ['weeks' => [], 'series' => []];
        $procCategoryTotals = null;

        if ($code) {
            $recepBySpecies = \App\Models\Recepcion::selectRaw('n_especie as especie, SUM(peso_neto) as kilos')
                ->where('id_emisor', $code)
                ->groupBy('n_especie')
                ->orderByDesc('kilos')
                ->limit(10)
                ->get();

            $procStackBySpecies = \App\Models\Proceso::selectRaw('especie, SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho')
                ->where('c_productor', $code)
                ->groupBy('especie')
                ->orderByDesc(DB::raw('SUM(exp)+SUM(comercial)+SUM(merma)+SUM(desecho)'))
                ->limit(10)
                ->get();

            $recepWeeklyRaw = \App\Models\Recepcion::selectRaw("DATE_FORMAT(fecha_g_recepcion, '%x-%v') as semana, n_especie as especie, SUM(peso_neto) as kilos, MIN(fecha_g_recepcion) as min_fecha")
                ->where('id_emisor', $code)
                ->groupBy(DB::raw("DATE_FORMAT(fecha_g_recepcion, '%x-%v')"), 'n_especie')
                ->orderBy('min_fecha')
                ->limit(200)
                ->get();

            $weeks = $recepWeeklyRaw->pluck('semana')->unique()->values()->all();
            sort($weeks);
            $speciesList = $recepWeeklyRaw->pluck('especie')->unique()->values();

            $series = [];
            foreach ($speciesList as $sp) {
                $points = [];
                foreach ($weeks as $wk) {
                    $val = $recepWeeklyRaw->firstWhere(fn ($r) => $r->especie === $sp && $r->semana === $wk);
                    $points[] = $val ? (int) $val->kilos : 0;
                }
                $series[] = ['name' => $sp, 'data' => $points];
            }

            $recepWeeklyBySpecies = [
                'weeks' => $weeks,
                'series' => $series,
            ];

            $procCategoryTotals = \App\Models\Proceso::selectRaw('SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho')
                ->where('c_productor', $code)
                ->first();
        }

        return Inertia::render('Producers/Dashboard', [
            'producer' => $producer,
            'recepciones' => $recepciones,
            'procesos' => $procesos,
            'certifications' => $certifications,
            'markets' => $markets,
            'contracts' => $contracts,
            'stats' => [
                'recepciones' => $recepciones->count(),
                'procesos' => $procesos->count(),
                'certifications' => $certifications->count(),
                'contracts' => $contracts->count(),
            ],
            'charts' => [
                'recepBySpecies' => $recepBySpecies,
                'procStackBySpecies' => $procStackBySpecies,
                'recepWeeklyBySpecies' => $recepWeeklyBySpecies,
                'procCategoryTotals' => $procCategoryTotals,
            ],
        ]);
    }
}





