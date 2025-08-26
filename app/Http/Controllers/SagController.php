<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SagCertification; // Added
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage; // Added
use Illuminate\Support\Facades\Response; // Added

class SagController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->input('perPage', 10); // Default to 10 items per page

        // Get unique RUTs that match the search criteria
        $query = User::select('rut')
                     ->whereNotNull('rut')
                     ->whereNotNull('csg')
                     ->distinct();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('rut', 'like', '%' . $search . '%')
                  ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        // Paginate the unique RUTs
        $paginatedRuts = $query->paginate($perPage);

        // Get all User records (CSGs) for the current page's RUTs
        $rutsOnCurrentPage = $paginatedRuts->pluck('rut')->toArray();

        $producersData = User::whereIn('rut', $rutsOnCurrentPage)
                             ->whereNotNull('csg')
                             ->with('especies', 'csgEspecieCountryStatuses') // sagCertifications removed from here
                             ->get()
                             ->groupBy('rut');

        // Fetch all SAG Certifications for the producers on the current page
        $allProducerSagCertifications = SagCertification::whereIn('producer_rut', $rutsOnCurrentPage)->get()->groupBy('producer_rut');


        // Transform the grouped data for the frontend
        $producers = $producersData->map(function ($csgUsers, $rut) use ($allProducerSagCertifications) {
            $producerName = $csgUsers->first()->name;

            $csgDetails = $csgUsers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'csg_code' => $user->csg,
                    'especies' => $user->especies->map(fn($especie) => ['id' => $especie->id, 'name' => $especie->name]),
                    // 'sag_certifications_count' removed from here
                ];
            });

            $producerTotalCertifications = $allProducerSagCertifications->has($rut) ? $allProducerSagCertifications[$rut]->count() : 0;

            return [
                'rut' => $rut,
                'name' => $producerName,
                'csg_records' => $csgDetails,
                'sag_certifications_count' => $producerTotalCertifications, // Added at producer level
            ];
        })->values();

        // Manually create a LengthAwarePaginator for the transformed data
        // This is necessary because we paginated RUTs first, then fetched related data
        $paginatedProducers = new \Illuminate\Pagination\LengthAwarePaginator(
            $producers,
            $paginatedRuts->total(),
            $paginatedRuts->perPage(),
            $paginatedRuts->currentPage(),
            ['path' => $paginatedRuts->path()]
        );


        return Inertia::render('Sag/Index', [
            'producers' => $paginatedProducers, // Pass the paginated producers
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function show(string $rut)
    {
        // Fetch all user records for the given RUT
        $producerCsgRecords = User::where('rut', $rut)
                                  ->whereNotNull('csg') // Ensure it's a CSG record
                                  ->with('especies', 'csgEspecieCountryStatuses') // sagCertifications removed from here
                                  ->get();

        if ($producerCsgRecords->isEmpty()) {
            abort(404, 'Productor no encontrado.');
        }

        // The producer's name can be taken from any of the records
        $producerName = $producerCsgRecords->first()->name;

        // Fetch SAG Certifications for the producer (by RUT)
        $producerSagCertifications = SagCertification::where('producer_rut', $rut)->get();


        return Inertia::render('Sag/Show', [
            'producerRut' => $rut,
            'producerName' => $producerName,
            'producerSagCertifications' => $producerSagCertifications->map(function ($cert) {
                return [
                    'id' => $cert->id,
                    'name' => $cert->name,
                    'description' => $cert->description,
                    'file_path' => $cert->file_path,
                    'issue_date' => $cert->issue_date,
                    'expiration_date' => $cert->expiration_date,
                    'certification_type' => $cert->certification_type,
                ];
            }),
            'csgRecords' => $producerCsgRecords->map(function ($user) {
                return [
                    'id' => $user->id,
                    'csg_code' => $user->csg,
                    'especies' => $user->especies->map(fn($especie) => ['id' => $especie->id, 'name' => $especie->name]),
                    // sag_certifications removed from here as it's now at producer level
                    'csg_especie_country_statuses' => $user->csgEspecieCountryStatuses->map(function ($status) {
                        return [
                            'id' => $status->id,
                            'especie_id' => $status->especie_id,
                            'country_id' => 'country_id',
                            'status' => $status->status,
                            'last_updated_at' => $status->last_updated_at,
                        ];
                    }),
                ];
            }),
        ]);
    }

    public function updateCountryAuthorizationStatus(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id', // The CSG record ID
            'especie_id' => 'required|exists:especies,id',
            'country_id' => 'required|exists:countries,id',
            'status' => 'required|in:Autorizado,Pendiente,No Autorizado',
        ]);

        // Find or create the record
        $statusRecord = \App\Models\CsgEspecieCountryStatus::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'especie_id' => $validated['especie_id'],
                'country_id' => $validated['country_id'],
            ],
            [
                'status' => $validated['status'],
                'last_updated_at' => now(),
            ]
        );

        return back();
    }

    public function uploadCertification(Request $request)
    {
        $validated = $request->validate([
            'producer_rut' => 'required|string|exists:users,rut', // Changed from user_id to producer_rut
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240', // Max 10MB
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'issue_date' => 'required|date',
            'expiration_date' => 'nullable|date|after_or_equal:issue_date',
            'certification_type' => 'required|string|in:Certificacion SAG,Application', // Assuming these types
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('sag_certifications', $fileName, 'public'); // Store in storage/app/public/sag_certifications

        SagCertification::create([
            'producer_rut' => $validated['producer_rut'], // Changed from user_id
            'name' => $validated['name'],
            'description' => $validated['description'],
            'file_path' => $filePath,
            'issue_date' => $validated['issue_date'],
            'expiration_date' => $validated['expiration_date'],
            'certification_type' => $validated['certification_type'],
        ]);

        return back()->with('success', 'Certificación/Aplicación subida exitosamente.');
    }

    public function downloadCertification(SagCertification $certification)
    {
        if (Storage::disk('public')->exists($certification->file_path)) {
            return Storage::disk('public')->download($certification->file_path, $certification->name . '.' . pathinfo($certification->file_path, PATHINFO_EXTENSION));
        }

        abort(404, 'Archivo no encontrado.');
    }
}
