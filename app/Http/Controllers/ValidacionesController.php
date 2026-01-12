<?php

namespace App\Http\Controllers;

use App\Models\Recepcion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ValidacionesController extends Controller
{
    public function recepcionesSinContrato(Request $request)
    {
        $excludedServiceIds = [1, 2, 3, 5, 7, 8];

        $query = User::role('Productor')
            ->whereNotNull('idprod')
            ->whereIn('idprod', Recepcion::query()->select('id_emisor')->distinct())
            ->whereDoesntHave('contracts')
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

        $producers = $query
            ->withCount('recepciones')
            ->withMax('recepciones', 'fecha_g_recepcion')
            ->with('services:id,name')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Validaciones/RecepcionesSinContrato', [
            'producers' => $producers->through(function ($producer) {
                $lastReception = $producer->recepciones_max_fecha_g_recepcion
                    ? Carbon::parse($producer->recepciones_max_fecha_g_recepcion)->format('Y-m-d')
                    : null;

                return [
                    'id' => $producer->id,
                    'name' => $producer->name,
                    'rut' => $producer->rut,
                    'email' => $producer->email,
                    'idprod' => $producer->idprod,
                    'recepciones_count' => $producer->recepciones_count ?? 0,
                    'last_reception_date' => $lastReception,
                    'services' => $producer->services->map(function ($service) {
                        return [
                            'id' => $service->id,
                            'name' => $service->name,
                        ];
                    }),
                ];
            }),
            'filters' => $request->only('search'),
            'excludedServices' => $excludedServiceIds,
        ]);
    }
}
