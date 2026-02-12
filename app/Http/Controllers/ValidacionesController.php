<?php

namespace App\Http\Controllers;

use App\Models\Recepcion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ValidacionesController extends Controller
{
    public function recepcionesSinContrato(Request $request)
    {
        $user = $request->user();
        $allowedRoles = ['Planificador', 'Administración', 'Gerencia de Planta', 'Administrador'];
        if (! $user || ! method_exists($user, 'hasAnyRole') || ! $user->hasAnyRole($allowedRoles)) {
            abort(403);
        }

        $excludedServiceIds = [1, 2, 3, 5, 7, 8];

        $query = User::role('Productor')
            ->whereNotNull('idprod')
            ->whereDoesntHave('services', function ($serviceQuery) use ($excludedServiceIds) {
                $serviceQuery->whereIn('services.id', $excludedServiceIds);
            });

        if ($search = $request->input('search')) {
            $query->where(function ($filter) use ($search) {
                $filter->where('name', 'like', '%'.$search.'%')
                    ->orWhere('rut', 'like', '%'.$search.'%')
                    ->orWhere('idprod', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $emailFilter = $request->input('email_filter', 'all');
        if ($emailFilter === 'with') {
            $query->whereNotNull('email')
                ->where('email', '<>', '')
                ->where('email', 'not like', '%@sync.greene.cl');
        } elseif ($emailFilter === 'without') {
            $query->where(function ($filter) {
                $filter->whereNull('email')
                    ->orWhere('email', '')
                    ->orWhere('email', 'like', '%@sync.greene.cl');
            });
        }

        $phoneFilter = $request->input('phone_filter', 'all');
        if ($phoneFilter === 'with') {
            $query->whereHas('telefonos');
        } elseif ($phoneFilter === 'without') {
            $query->whereDoesntHave('telefonos');
        }

        $rutsWithContractsQuery = User::query()
            ->select('rut')
            ->whereNotNull('rut')
            ->where('rut', '<>', '')
            ->whereHas('contracts')
            ->distinct();

        $contractFilter = $request->input('contract_filter', 'all');
        if ($contractFilter === 'with') {
            $query->where(function ($filter) use ($rutsWithContractsQuery) {
                $filter->whereIn('rut', $rutsWithContractsQuery)
                    ->orWhere(function ($rutless) {
                        $rutless->where(function ($noRut) {
                            $noRut->whereNull('rut')
                                ->orWhere('rut', '');
                        })->whereHas('contracts');
                    });
            });
        } elseif ($contractFilter === 'without') {
            $query->where(function ($filter) use ($rutsWithContractsQuery) {
                $filter->where(function ($withRut) use ($rutsWithContractsQuery) {
                    $withRut->whereNotNull('rut')
                        ->where('rut', '<>', '')
                        ->whereNotIn('rut', $rutsWithContractsQuery);
                })->orWhere(function ($rutless) {
                    $rutless->where(function ($noRut) {
                        $noRut->whereNull('rut')
                            ->orWhere('rut', '');
                    })->whereDoesntHave('contracts');
                });
            });
        }

        $producers = $query->where('is_active', true)
            //->withCount('recepciones')
            ->withCount('contracts')
            //->withMax('recepciones', 'fecha_g_recepcion')
            ->with('services:id,name')
            ->with('telefonos:id,user_id,numero')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $rutsOnPage = $producers->getCollection()
            ->pluck('rut')
            ->filter()
            ->unique()
            ->values();

        $rutsWithContracts = $rutsOnPage->isEmpty()
            ? collect()
            : User::query()
                ->whereIn('rut', $rutsOnPage)
                ->whereHas('contracts')
                ->pluck('rut')
                ->unique();

        $hasContractsByRut = $rutsWithContracts->flip();

        return Inertia::render('Validaciones/RecepcionesSinContrato', [
            'producers' => $producers->through(function ($producer) use ($hasContractsByRut) {
                // $lastReception = $producer->recepciones_max_fecha_g_recepcion
                //     ? Carbon::parse($producer->recepciones_max_fecha_g_recepcion)->format('Y-m-d')
                //     : null;

                $email = $producer->email;
                if ($email && Str::endsWith(Str::lower($email), '@sync.greenex.cl')) {
                    $email = null;
                }

                $hasEmail = (bool) $email;
                $hasPhone = $producer->telefonos->isNotEmpty();
                $hasContract = $producer->rut
                    ? isset($hasContractsByRut[$producer->rut])
                    : (int) ($producer->contracts_count ?? 0) > 0;

                return [
                    'id' => $producer->id,
                    'name' => $producer->name,
                    'rut' => $producer->rut,
                    'email' => $email,
                    'idprod' => $producer->idprod,
                    'recepciones_count' => $producer->recepciones_count ?? 0,
                    //'last_reception_date' => $lastReception,
                    'telefonos' => $producer->telefonos->pluck('numero')->filter()->values(),
                    'has_email' => $hasEmail,
                    'has_phone' => $hasPhone,
                    'has_contract' => $hasContract,
                    'services' => $producer->services->map(function ($service) {
                        return [
                            'id' => $service->id,
                            'name' => $service->name,
                        ];
                    }),
                ];
            }),
            'filters' => $request->only('search', 'email_filter', 'phone_filter', 'contract_filter'),
            'excludedServices' => $excludedServiceIds,
        ]);
    }
}
