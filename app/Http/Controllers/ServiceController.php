<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use App\Models\Recepcion;
use App\Models\Proceso;
use App\Models\ProducerCertification;
use App\Models\CsgEspecieCountryStatus;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $services = Service::with('users', 'owner', 'phones', 'emails')->get();

        $search = $request->input('search');
        // For the index + modal flow, we need users (only active) list;
        // the modal itself will exclude users already assigned to the selected service.
        $availableUsersQuery = User::query()->where('is_active', true);

        if ($search) {
            $availableUsersQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }


        $availableUsers = $availableUsersQuery->get();

        // If the authenticated user is a producer (idprod not null), load their recepciones and procesos
        $myRecepciones = collect();
        $myProcesos = collect();
        if (auth()->check() && ! empty(auth()->user()->idprod)) {
            $producerCode = auth()->user()->idprod;
            $producerName = auth()->user()->name;
            Log::info("producerCode: $producerCode, producerName: $producerName");
            $myRecepciones = \App\Models\Recepcion::query()
                ->where('id_emisor', $producerCode)
                ->orderByDesc('fecha_g_recepcion')
                ->limit(50)
                ->get(['id','numero_g_recepcion','fecha_g_recepcion','n_especie','n_variedad','cantidad','peso_neto','informe']);
            $myProcesos = \App\Models\Proceso::query()
                ->where('agricola', $producerName)
                ->orderByDesc('fecha')
                ->limit(50)
                ->get(['id','n_proceso','fecha','especie','variedad','kilos_netos','informe','exp','comercial','merma']);
        }

        return Inertia::render('Services/Index', [
            'services' => $services,
            'availableUsers' => $availableUsers,
            'filters' => [
                'search' => $search,
            ],
            'myRecepciones' => $myRecepciones,
            'myProcesos' => $myProcesos,
        ]);
    }

    public function create()
    {
        return Inertia::render('Services/Create', [
            'users' => User::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'required|exists:users,id',
            'phones' => 'present|array',
            'phones.*' => 'nullable|string|max:255',
            'emails' => 'present|array',
            'emails.*' => 'nullable|email|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $service = Service::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'owner_id' => $validated['owner_id'],
            ]);

            if (! empty($validated['phones'])) {
                foreach ($validated['phones'] as $phone) {
                    if ($phone) { // Ensure phone is not null or empty
                        $service->phones()->create(['phone' => $phone]);
                    }
                }
            }

            if (! empty($validated['emails'])) {
                foreach ($validated['emails'] as $email) {
                    if ($email) { // Ensure email is not null or empty
                        $service->emails()->create(['email' => $email]);
                    }
                }
            }
        });

        return redirect()->route('services.index');
    }

    public function show(Service $service)
    {
        $service->load('users');
        $availableUsers = User::where('is_active', true)->whereDoesntHave('services', function ($query) use ($service) {
            $query->where('service_id', $service->id);
        })->get();

        // Aggregate recepciones y procesos de los usuarios asociados al servicio
        $producerCodes = $service->users->pluck('idprod')->filter()->values();
        $serviceRecepciones = collect();
        $serviceProcesos = collect();
        if ($producerCodes->isNotEmpty()) {
            $serviceRecepciones = \App\Models\Recepcion::query()
                ->whereIn('id_emisor', $producerCodes)
                ->orderByDesc('fecha_g_recepcion')
                ->limit(100)
                ->get(['id','numero_g_recepcion','fecha_g_recepcion','n_especie','n_variedad','n_emisor','cantidad','peso_neto','informe']);
            $serviceProcesos = \App\Models\Proceso::query()
                ->whereIn('c_productor', $producerCodes)
                ->orderByDesc('fecha')
                ->limit(100)
                ->get(['id','n_proceso','fecha','especie','variedad','kilos_netos','informe','exp','comercial','merma','c_productor']);
        }

        return Inertia::render('Services/Show', [
            'service' => $service,
            'availableUsers' => $availableUsers,
            'recepciones' => $serviceRecepciones,
            'procesos' => $serviceProcesos,
        ]);
    }

    public function edit(Service $service)
    {
        $service->load('owner', 'phones', 'emails');

        return Inertia::render('Services/Edit', [
            'service' => $service,
            'users' => User::all(),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'required|exists:users,id',
            'phones' => 'present|array',
            'phones.*' => 'nullable|string|max:255',
            'emails' => 'present|array',
            'emails.*' => 'nullable|email|max:255',
        ]);
        Log::info('Update Service Request:', $validated);
        DB::transaction(function () use ($validated, $service) {
            $service->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'owner_id' => $validated['owner_id'],
            ]);

            // Sync phones
            $service->phones()->delete();
            if (! empty($validated['phones'])) {
                foreach ($validated['phones'] as $phone) {
                    if ($phone) {
                        $service->phones()->create(['phone' => $phone]);
                    }
                }
            }

            // Sync emails
            $service->emails()->delete();
            if (! empty($validated['emails'])) {
                foreach ($validated['emails'] as $email) {
                    if ($email) {
                        $service->emails()->create(['email' => $email]);
                    }
                }
            }
        });

        return redirect()->route('services.index');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return redirect()->route('services.index');
    }

    public function attachUser(Request $request, Service $service)
    {
        Log::info('Attach User Request:', $request->all());
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Check if the user is already attached to this service to prevent duplicate entry errors
        if (! $service->users()->where('user_id', $request->user_id)->exists()) {
            $service->users()->attach($request->user_id);
        }

        return redirect()->route('services.index', [
            'page' => $request->input('page'),
            'search' => $request->input('search'),
        ]);
    }

    public function detachUser(Service $service, User $user, Request $request)
    {
        Log::info('Detach User Request:', $request->all());
        $service->users()->detach($user->id);

        return redirect()->route('services.index', [
            'page' => $request->input('page'),
            'search' => $request->input('search'),
        ]);
    }

    public function getServiceProducers(Service $service, Request $request)
    {
        return response()->json([
            'test_message' => 'Data received successfully!',
        ]);
    }

    public function dashboard(Service $service)
    {
        $service->load('users');
        $producerIds = $service->users->pluck('id');
        $producerCodes = $service->users->pluck('idprod')->filter()->values();

        $producerNames = $service->users->pluck('name')->filter()->values();

        $recepciones = collect();
        $procesos = collect();
        if ($producerCodes->isNotEmpty()) {
            $recepciones = Recepcion::query()
                ->whereIn('id_emisor', $producerCodes)
                ->orderByDesc('fecha_g_recepcion')
                ->limit(50)
                ->get(['id','numero_g_recepcion','fecha_g_recepcion','n_especie','n_variedad','n_emisor','cantidad','peso_neto','informe']);
            $procesos = Proceso::query()
                ->whereIn('agricola', $producerNames)
                ->orderByDesc('fecha')
                ->limit(50)
                ->get(['id','n_proceso','fecha','especie','variedad','kilos_netos','informe','exp','comercial','merma','c_productor']);
        }

        $certifications = ProducerCertification::with(['certifyingHouse','certificateType','especie','user:id,name'])
            ->whereIn('user_id', $producerIds)
            ->orderByDesc('expiration_date')
            ->limit(100)
            ->get();

        $markets = CsgEspecieCountryStatus::with(['user:id,name','especie:id,name','country:id,name'])
            ->whereIn('user_id', $producerIds)
            ->limit(200)
            ->get();

        $contracts = Contract::whereIn('user_id', $producerIds)
            ->orderByDesc('fecha_contrato')
            ->get(['id','user_id','contract_file_path','fecha_contrato','vencimiento','comision']);

        $recepBySpecies = collect();
        $procStackBySpecies = collect();
        $recepWeeklyBySpecies = ['weeks' => [], 'series' => []];
        $procCategoryTotals = null;

        if ($producerCodes->isNotEmpty()) {
            $recepBySpecies = Recepcion::selectRaw('n_especie as especie, SUM(peso_neto) as kilos')
                ->whereIn('id_emisor', $producerCodes)
                ->groupBy('n_especie')
                ->orderByDesc('kilos')
                ->limit(10)
                ->get();

            $procStackBySpecies = Proceso::selectRaw('especie, SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho')
                ->whereIn('agricola', $producerNames)
                ->groupBy('especie')
                ->orderByDesc(DB::raw('SUM(exp)+SUM(comercial)+SUM(merma)+SUM(desecho)'))
                ->limit(10)
                ->get();

            $recepWeeklyRaw = Recepcion::selectRaw("DATE_FORMAT(fecha_g_recepcion, '%x-%v') as semana, n_especie as especie, SUM(peso_neto) as kilos, MIN(fecha_g_recepcion) as min_fecha")
                ->whereIn('id_emisor', $producerCodes)
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

            $procCategoryTotals = Proceso::selectRaw('SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho')
                ->whereIn('agricola', $producerNames)
                ->first();
        }
        return Inertia::render('Services/Dashboard', [
            'service' => $service,
            'recepciones' => $recepciones,
            'procesos' => $procesos,
            'certifications' => $certifications,
            'markets' => $markets,
            'contracts' => $contracts,
            'stats' => [
                'producers' => $service->users->count(),
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
