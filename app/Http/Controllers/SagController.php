<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Especie; // Added
use App\Models\SagCertification;
use App\Models\User;
use Illuminate\Http\Request; // Added
// Added
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SagController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $statusFilter = $request->input('status', 'all');
        $perPage = $request->input('perPage', 10); // Default to 10 items per page

        // Get unique RUTs that match the search criteria
        $query = User::select('rut')
            ->whereNotNull('rut')
            ->whereNotNull('csg')
            ->distinct();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('rut', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
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

        // Fetch all SAG Certifications for the producers on the current page (legacy by RUT)
        $allProducerSagCertifications = SagCertification::whereIn('producer_rut', $rutsOnCurrentPage)->get()->groupBy('producer_rut');

        // Also count certifications by CSG (new linkage via csg_user_id) and status breakdown
        $csgUserIds = $producersData->flatten()->pluck('id')->unique()->values();
        $certsByCsg = SagCertification::whereIn('csg_user_id', $csgUserIds)->get()->groupBy('csg_user_id');
        $certCountsByCsg = collect();
        $certStatusByCsg = collect();
        $kpiValid = 0;
        $kpiExpSoon = 0;
        $kpiExpired = 0;
        $kpiTotal = 0;
        foreach ($certsByCsg as $csgId => $rows) {
            $total = $rows->count();
            $expired = 0;
            $expSoon = 0;
            $valid = 0;
            foreach ($rows as $cert) {
                if ($cert->expiration_date) {
                    $diff = now()->diffInDays(\Carbon\Carbon::parse($cert->expiration_date), false);
                    if ($diff < 0) {
                        $expired++;
                    } elseif ($diff <= 90) {
                        $expSoon++;
                    } else {
                        $valid++;
                    }
                }
            }
            $certCountsByCsg[$csgId] = $total;
            $certStatusByCsg[$csgId] = [
                'expired' => $expired,
                'expiring_soon' => $expSoon,
                'valid' => $valid,
            ];
            $kpiTotal += $total;
            $kpiValid += $valid;
            $kpiExpSoon += $expSoon;
            $kpiExpired += $expired;
        }

        // Transform the grouped data for the frontend
        $producers = $producersData->map(function ($csgUsers, $rut) use ($allProducerSagCertifications, $certCountsByCsg, $certStatusByCsg) {
            $producerName = $csgUsers->first()->name;

            $csgDetails = $csgUsers->map(function ($user) use ($certCountsByCsg, $certStatusByCsg) {
                return [
                    'id' => $user->id,
                    'csg_code' => $user->csg,
                    'especies' => $user->especies->map(fn ($especie) => ['id' => $especie->id, 'name' => $especie->name]),
                    'sag_certifications_count' => (int) ($certCountsByCsg[$user->id] ?? 0),
                    'sag_certifications_status' => $certStatusByCsg[$user->id] ?? ['expired' => 0, 'expiring_soon' => 0, 'valid' => 0],
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
                'status' => $statusFilter,
            ],
            'kpis' => [
                'valid' => $kpiValid,
                'expiring_soon' => $kpiExpSoon,
                'expired' => $kpiExpired,
                'total' => $kpiTotal,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $search = $request->input('search');
        $statusFilter = $request->input('status', 'all');

        $rutQuery = User::select('rut')
            ->whereNotNull('rut')
            ->whereNotNull('csg')
            ->distinct();

        if ($search) {
            $rutQuery->where(function ($q) use ($search) {
                $q->where('rut', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $ruts = $rutQuery->pluck('rut')->toArray();
        $csgUsers = User::whereIn('rut', $ruts)->whereNotNull('csg')->with('especies')->get();

        // Build counts
        $csgUserIds = $csgUsers->pluck('id');
        $certs = SagCertification::whereIn('csg_user_id', $csgUserIds)->get()->groupBy('csg_user_id');

        $rows = [];
        foreach ($csgUsers as $user) {
            $byCsg = $certs->get($user->id, collect());
            $total = $byCsg->count();
            $expired = 0;
            $expSoon = 0;
            $valid = 0;
            foreach ($byCsg as $cert) {
                if ($cert->expiration_date) {
                    $diff = now()->diffInDays(\Carbon\Carbon::parse($cert->expiration_date), false);
                    if ($diff < 0) {
                        $expired++;
                    } elseif ($diff <= 90) {
                        $expSoon++;
                    } else {
                        $valid++;
                    }
                }
            }

            // Status filter
            $include = true;
            if ($statusFilter === 'Vigente') {
                $include = $valid > 0;
            } elseif ($statusFilter === 'Por vencer') {
                $include = $expSoon > 0;
            } elseif ($statusFilter === 'Vencida') {
                $include = $expired > 0;
            }

            if (! $include) {
                continue;
            }

            $rows[] = [
                'rut' => $user->rut,
                'productor' => $user->name,
                'csg' => $user->csg,
                'vigentes' => $valid,
                'por_vencer' => $expSoon,
                'vencidas' => $expired,
                'total' => $total,
            ];
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sag_certificaciones.csv"',
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['RUT', 'Productor', 'CSG', 'Vigentes', 'Por vencer', 'Vencidas', 'Total']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['rut'], $r['productor'], $r['csg'], $r['vigentes'], $r['por_vencer'], $r['vencidas'], $r['total']]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
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

        // CSG ids for this producer
        $csgIds = $producerCsgRecords->pluck('id');

        // Fetch SAG Certifications for the producer: include legacy by RUT and new by CSG IDs
        $producerSagCertifications = SagCertification::with(['sdps', 'csgUser:id,csg'])
            ->where('producer_rut', $rut)
            ->orWhereIn('csg_user_id', $csgIds)
            ->get();

        // Fetch SDP sites for each CSG record
        $sdpSitesByCsg = \App\Models\SdpSite::whereIn('csg_user_id', $csgIds)->get()->groupBy('csg_user_id');

        return Inertia::render('Sag/Show', [
            'producerRut' => $rut,
            'producerName' => $producerName,
            'producerSagCertifications' => $producerSagCertifications->map(function ($cert) {
                $status = 'N/A';
                $days = null;
                if ($cert->expiration_date) {
                    $days = now()->diffInDays(\Carbon\Carbon::parse($cert->expiration_date), false);
                    if ($days < 0) {
                        $status = 'Vencida';
                    } elseif ($days <= 90) {
                        $status = 'Por vencer';
                    } else {
                        $status = 'Vigente';
                    }
                }

                return [
                    'id' => $cert->id,
                    'name' => $cert->name,
                    'description' => $cert->description,
                    'file_path' => $cert->file_path,
                    'issue_date' => $cert->issue_date,
                    'expiration_date' => $cert->expiration_date,
                    'certification_type' => $cert->certification_type,
                    'especie_id' => $cert->especie_id,
                    'country_id' => $cert->country_id,
                    'is_active' => (bool) $cert->is_active,
                    'csg_code' => $cert->csgUser?->csg,
                    'sdps' => $cert->sdps->map(fn ($s) => [
                        'id' => $s->id,
                        'code' => $s->code,
                        'name' => $s->name,
                    ]),
                    'status' => $status,
                    'days_to_expiration' => $days,
                ];
            }),
            'especies' => Especie::select('id', 'name')->orderBy('name')->get(),
            'countries' => Country::select('id', 'name')->orderBy('name')->get(),
            'csgRecords' => $producerCsgRecords->map(function ($user) use ($sdpSitesByCsg) {
                return [
                    'id' => $user->id,
                    'csg_code' => $user->csg,
                    'especies' => $user->especies->map(fn ($especie) => ['id' => $especie->id, 'name' => $especie->name]),
                    // sag_certifications removed from here as it's now at producer level
                    'csg_especie_country_statuses' => $user->csgEspecieCountryStatuses->map(function ($status) {
                        return [
                            'id' => $status->id,
                            'especie_id' => $status->especie_id,
                            'country_id' => $status->country_id,
                            'status' => $status->status,
                            'last_updated_at' => $status->last_updated_at,
                        ];
                    }),
                    'sdp_sites' => ($sdpSitesByCsg[$user->id] ?? collect())->map(function ($sdp) {
                        return [
                            'id' => $sdp->id,
                            'code' => $sdp->code,
                            'name' => $sdp->name,
                            'address' => $sdp->address,
                            'is_active' => (bool) $sdp->is_active,
                        ];
                    })->values(),
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
            'csg_user_id' => 'required|exists:users,id',
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240', // Max 10MB
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'issue_date' => 'required|date',
            'expiration_date' => 'nullable|date|after_or_equal:issue_date',
            'certification_type' => 'required|string|in:Certificacion SAG,Application', // Assuming these types
            'sdp_site_ids' => 'array',
            'sdp_site_ids.*' => 'exists:sdp_sites,id',
            'especie_id' => 'nullable|exists:especies,id',
            'country_id' => 'nullable|exists:countries,id',
            'is_active' => 'nullable|boolean',
        ]);

        // Ensure provided SDP sites belong to the selected CSG
        if (! empty($validated['sdp_site_ids'])) {
            $validCount = \App\Models\SdpSite::where('csg_user_id', $validated['csg_user_id'])
                ->whereIn('id', $validated['sdp_site_ids'])
                ->count();
            if ($validCount !== count($validated['sdp_site_ids'])) {
                return back()->withErrors(['sdp_site_ids' => 'Uno o más SDP no pertenecen al CSG seleccionado.'])->withInput();
            }
        }

        $file = $request->file('file');
        $fileName = time().'_'.$file->getClientOriginalName();
        $filePath = $file->storeAs('sag_certifications', $fileName, 'public'); // Store in storage/app/public/sag_certifications

        // Derive producer RUT from the selected CSG user to satisfy legacy NOT NULL column
        $csgUser = User::select('rut')->find($validated['csg_user_id']);
        $producerRut = $csgUser?->rut;

        $cert = SagCertification::create([
            'producer_rut' => $producerRut,
            'csg_user_id' => $validated['csg_user_id'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'file_path' => $filePath,
            'issue_date' => $validated['issue_date'],
            'expiration_date' => $validated['expiration_date'],
            'certification_type' => $validated['certification_type'],
            'especie_id' => $validated['especie_id'] ?? null,
            'country_id' => $validated['country_id'] ?? null,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ]);

        if (! empty($validated['sdp_site_ids'])) {
            $cert->sdps()->sync($validated['sdp_site_ids']);
        }

        return back()->with('success', 'Certificación/Aplicación subida exitosamente.');
    }

    public function setCertificationActive(Request $request, SagCertification $certification)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $certification->update(['is_active' => $data['is_active']]);

        return back()->with('success', 'Estado de vigencia actualizado.');
    }

    public function downloadCertification(SagCertification $certification)
    {
        if (Storage::disk('public')->exists($certification->file_path)) {
            return Storage::disk('public')->download($certification->file_path, $certification->name.'.'.pathinfo($certification->file_path, PATHINFO_EXTENSION));
        }

        abort(404, 'Archivo no encontrado.');
    }

    public function destroyCertification(SagCertification $certification)
    {
        // Detach SDPs
        if (method_exists($certification, 'sdps')) {
            $certification->sdps()->detach();
        }

        // Delete physical file if present
        if ($certification->file_path && Storage::disk('public')->exists($certification->file_path)) {
            Storage::disk('public')->delete($certification->file_path);
        }

        $certification->delete();

        return back()->with('success', 'Certificación eliminada correctamente.');
    }
}
