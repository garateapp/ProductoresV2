<?php

namespace App\Http\Controllers;

use App\Models\ProducerCertification;
use App\Models\CertifyingHouse;
use App\Models\CertificateType;
use App\Models\Especie;
use App\Models\CertificationOtherDocument;
use App\Models\User; // Import User model
use App\Models\Market;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class ProducerCertificationController extends Controller
{
        public function index(Request $request)
        {
            $search = $request->input('search');

            $query = User::whereNotNull('idprod');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%')
                      ->orWhere('rut', 'like', '%' . $search . '%');
                });
            }

            // Eager load certifications to avoid N+1 problem during KPI calculation
            $allProducers = $query->with('certifications')->get();

            $producersByRut = $allProducers->groupBy('rut');

            // KPI Calculations
            $totalProducers = $producersByRut->count();
            $producersUpToDate = 0;
            $producersExpiringSoon = 0;
            $producersExpired = 0;

            foreach ($producersByRut as $rut => $producerGroup) {
                $hasExpired = false;
                $hasExpiringSoon = false;
                $hasUpToDate = false;

                foreach ($producerGroup as $producer) {
                    if ($producer->certifications->isEmpty()) {
                        continue;
                    }

                    foreach ($producer->certifications as $certification) {
                        $status = $this->getCertificationStatus($certification->expiration_date);

                        if ($status === 'Vencida') {
                            $hasExpired = true;
                        } elseif ($status === 'Por vencer') {
                            $hasExpiringSoon = true;
                        } elseif ($status === 'Vigente') {
                            $hasUpToDate = true;
                        }
                    }
                }

                if ($hasExpired) {
                    $producersExpired++;
                } elseif ($hasExpiringSoon) {
                    $producersExpiringSoon++;
                } elseif ($hasUpToDate) {
                    $producersUpToDate++;
                }
            }

            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 25;
            $currentPageItems = $producersByRut->slice(($currentPage - 1) * $perPage, $perPage)->all();

            $paginatedProducers = new LengthAwarePaginator($currentPageItems, $producersByRut->count(), $perPage, $currentPage, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);

            return Inertia::render('Documentation/ProducerCertifications/Index', [
                'producers' => $paginatedProducers,
                'filters' => $request->only(['search']),
                'kpis' => [
                    'totalProducers' => $totalProducers,
                    'producersUpToDate' => $producersUpToDate,
                    'producersExpiringSoon' => $producersExpiringSoon,
                    'producersExpired' => $producersExpired,
                ],
            ]);
        }

        private function getCertificationStatus($expirationDate)
        {
            $today = now();
            $expiry = \Carbon\Carbon::parse($expirationDate);

            $diffDays=$today->diffInDays($expiry, false);

            if ($diffDays <= 30 && $diffDays > 0) {
                return 'Por vencer';
            } elseif ($diffDays < 0) {
                return 'Vencida';
            } else {
                return 'Vigente';
            }
        }

    public function create()
    {
        $certifyingHouses = CertifyingHouse::all();
        $certificateTypes = CertificateType::all();
        $especies = Especie::all();
        $producers = User::whereNotNull('idprod')->get(); // Get users who are producers

        return Inertia::render('Documentation/ProducerCertifications/Create', [
            'certifyingHouses' => $certifyingHouses,
            'certificateTypes' => $certificateTypes,
            'especies' => $especies,
            'producers' => $producers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'certifying_house_id' => 'required|exists:certifying_houses,id',
            'name' => 'required|string|max:255',
            'certificate_type_id' => 'required|exists:certificate_types,id',
            'certificate_code' => 'required|string|max:255|unique:producer_certifications,certificate_code',
            'especie_id' => 'required|exists:especies,id',
            'audit_date' => 'required|date',
            'expiration_date' => 'required|date|after:audit_date',
            'certificate_pdf' => 'nullable|file|mimes:pdf|max:10240',
            'other_documents' => 'array',
            'other_documents.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
            'has_pdf_extension' => 'boolean',
            'user_id' => 'nullable|exists:users,id', // Add validation for user_id
        ]);

        // Ensure the user_id belongs to a producer
        if (isset($validated['user_id']) && !User::where('id', $validated['user_id'])->whereNotNull('idprod')->exists()) {
            return redirect()->back()->withErrors(['user_id' => 'El usuario seleccionado no es un productor válido.']);
        }

        DB::transaction(function () use ($validated, $request) {
            $certificationData = $validated;
            $certificationData['certificate_pdf_path'] = null;

            if ($request->hasFile('certificate_pdf')) {
                $certificationData['certificate_pdf_path'] = $request->file('certificate_pdf')->store('certifications/pdfs', 'public');
            }

            $certification = ProducerCertification::create($certificationData);

            if ($request->hasFile('other_documents')) {
                foreach ($request->file('other_documents') as $file) {
                    $path = $file->store('certifications/other_documents', 'public');
                    $certification->otherDocuments()->create([
                        'file_path' => $path,
                        'description' => $file->getClientOriginalName(), // Or a more specific description
                    ]);
                }
            }
        });

        return redirect()->route('producer-certifications.show', $validated['user_id'])->with('success', 'Certificación creada exitosamente.');
    }

    public function edit(ProducerCertification $certification)
    {
        $certification->load(['certifyingHouse', 'certificateType', 'especie', 'otherDocuments', 'user']);
        $certifyingHouses = CertifyingHouse::all();
        $certificateTypes = CertificateType::all();
        $especies = Especie::all();
        $producers = User::whereNotNull('idprod')->get(); // Get users who are producers

        return Inertia::render('Documentation/ProducerCertifications/Edit', [
            'certification' => $certification,
            'certifyingHouses' => $certifyingHouses,
            'certificateTypes' => $certificateTypes,
            'especies' => $especies,
            'producers' => $producers,
        ]);
    }

    public function update(Request $request, ProducerCertification $certification)
    {
        $validated = $request->validate([
            'certifying_house_id' => 'required|exists:certifying_houses,id',
            'name' => 'required|string|max:255',
            'certificate_type_id' => 'required|exists:certificate_types,id',
            'certificate_code' => 'required|string|max:255|unique:producer_certifications,certificate_code,' . $certification->id,
            'especie_id' => 'required|exists:especies,id',
            'audit_date' => 'required|date',
            'expiration_date' => 'required|date|after:audit_date',
            'certificate_pdf' => 'nullable|file|mimes:pdf|max:10240',
            'other_documents' => 'array',
            'other_documents.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
            'has_pdf_extension' => 'boolean',
            'existing_other_documents' => 'array',
            'existing_other_documents.*.id' => 'required|exists:certification_other_documents,id',
            'existing_other_documents.*.description' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id', // Add validation for user_id
        ]);

        // Ensure the user_id belongs to a producer
        if (isset($validated['user_id']) && !User::where('id', $validated['user_id'])->whereNotNull('idprod')->exists()) {
            return redirect()->back()->withErrors(['user_id' => 'El usuario seleccionado no es un productor válido.']);
        }

        DB::transaction(function () use ($validated, $request, $certification) {
            $certificationData = $validated;

            if ($request->hasFile('certificate_pdf')) {
                // Delete old PDF if exists
                if ($certification->certificate_pdf_path) {
                    Storage::disk('public')->delete($certification->certificate_pdf_path);
                }
                $certificationData['certificate_pdf_path'] = $request->file('certificate_pdf')->store('certifications/pdfs', 'public');
            } else if ($request->boolean('remove_certificate_pdf')) { // Assuming a checkbox to remove PDF
                if ($certification->certificate_pdf_path) {
                    Storage::disk('public')->delete($certification->certificate_pdf_path);
                }
                $certificationData['certificate_pdf_path'] = null;
            }

            $certification->update($certificationData);

            // Sync other documents
            $existingDocumentIds = collect($validated['existing_other_documents'] ?? [])->pluck('id');
            $certification->otherDocuments()->whereNotIn('id', $existingDocumentIds)->each(function ($document) {
                Storage::disk('public')->delete($document->file_path);
                $document->delete();
            });

            if ($request->hasFile('other_documents')) {
                foreach ($request->file('other_documents') as $file) {
                    $path = $file->store('certifications/other_documents', 'public');
                    $certification->otherDocuments()->create([
                        'file_path' => $path,
                        'description' => $file->getClientOriginalName(),
                    ]);
                }
            }
        });

        return redirect()->route('producer-certifications.index')->with('success', 'Certificación actualizada exitosamente.');
    }

    public function destroy(ProducerCertification $certification)
    {
        DB::transaction(function () use ($certification) {
            // Delete main PDF
            if ($certification->certificate_pdf_path) {
                Storage::disk('public')->delete($certification->certificate_pdf_path);
            }
            // Delete other documents
            $certification->otherDocuments->each(function ($document) {
                Storage::disk('public')->delete($document->file_path);
                $document->delete();
            });
            $certification->delete();
        });

        return redirect()->route('producer-certifications.index')->with('success', 'Certificación eliminada exitosamente.');
    }

    public function show(User $producer)
    {
        $producer->load(['certifications.certifyingHouse', 'certifications.certificateType', 'certifications.especie', 'especies']);
        $certifyingHouses = CertifyingHouse::all();
        $certificateTypes = CertificateType::all();
        $especies = Especie::all();
        $markets = Market::with('country.continent', 'certificateTypes', 'authorizationType', 'especies')->where('is_active', true)->get();

        return Inertia::render('Documentation/ProducerCertifications/Show', [
            'producer' => $producer,
            'certifyingHouses' => $certifyingHouses,
            'certificateTypes' => $certificateTypes,
            'especies' => $especies,
            'markets' => $markets,
        ]);
    }

    public function downloadOtherDocument(CertificationOtherDocument $document)
    {
        return Storage::disk('public')->download($document->file_path, $document->description);
    }
}
