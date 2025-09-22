<?php

namespace App\Http\Controllers;

use App\Models\Especie;
use App\Models\Proceso;
use App\Models\Variedad;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProcesoController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isProducer = false;

        if (!empty($user->idprod)) {
            $isProducer = true;
        }

        $query = Proceso::query();

        // Base access scope: producer's own + service-associated producers (owner or member)
        $allowedProducerIds = collect();
        if (!empty($user->idprod)) {
            $allowedProducerIds->push($user->idprod);
        }
        // Services the user owns
       $ownedServiceUserIds = Service::where('owner_id', $user->id)
            ->with(['users:id,idprod'])
            ->get()
            ->pluck('users')
            ->flatten()
            ->pluck('idprod')
            ->filter();
        // Services the user belongs to
        $memberServiceUserIds = $user->services()->with(['users:name'])->get()
            ->pluck('users')
            ->flatten();


        $allowedProducerIds = $memberServiceUserIds->map(function ($id) {
            return $id->name;
        });


        if ($allowedProducerIds->isNotEmpty()) {
            $query->whereIn('agricola', $allowedProducerIds->all());
        }

        // General search filter
        if ($request->has('search') && $request->input('search') !== '' && $request->input('search') !== null) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('especie', 'like', '%'.$searchTerm.'%')
                    ->orWhere('variedad', 'like', '%'.$searchTerm.'%');
            });
        }

        $variedades = collect();

        // Filter by selected especie
        if ($request->has('especie_id') && $request->input('especie_id') !== '' && $request->input('especie_id') !== null) {
            $especie = Especie::find($request->input('especie_id'));
            if ($especie) {
                $query->where('especie', $especie->name);
                $variedades = $especie->variedads;
            }
        }

        // Filter by selected variedad
        if ($request->has('variedad_id') && $request->input('variedad_id') !== '' && $request->input('variedad_id') !== null) {
            $variedad = Variedad::find($request->input('variedad_id'));
            if ($variedad) {
                $query->where('variedad', $variedad->name);
            }
        }

        $totalProcesos = $query->count();
        $totalKgProcesados = (int) $query->sum('kilos_netos');
        $totalExportacion = (int) $query->sum('exp');
        $totalComercial = (int) $query->sum('comercial');
        $totalMerma = (int) $query->sum('merma');

        // Calculate totals for the chart
        $chartDataQuery = clone $query; // Clone the query
         $query->orderBy('fecha', 'desc');
        $chartData = $chartDataQuery->selectRaw('especie, SUM(exp) as exportacion, SUM(comercial) as comercial, SUM(desecho) as desecho, SUM(merma) as merma')
            ->groupBy('especie')
            ->get();

        $procesos = $query->paginate(10); // Use the original query for pagination

        $especies = Especie::all();

        // Filter species for producer if applicable
        if ($isProducer) {
            $producerEspeciesIds = $user->especies()->pluck('especie_id')->toArray();
            $especies = $especies->whereIn('id', $producerEspeciesIds)->values();
        }

        return Inertia::render('Procesos/Index', [
            'procesos' => $procesos,
            'especies' => $especies->toArray(),
            'variedades' => $variedades,
            'filters' => $request->only(['search', 'especie_id', 'variedad_id']),
            'isProducer' => $isProducer,
            'totalProcesos' => $totalProcesos,
            'totalKgProcesados' => $totalKgProcesados,
            'totalExportacion' => $totalExportacion,
            'totalComercial' => $totalComercial,
            'totalMerma' => $totalMerma,
            'chartData' => $chartData->toArray(), // Pass chart data to frontend
        ]);
    }
}
