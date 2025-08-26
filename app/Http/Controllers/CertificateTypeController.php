<?php

namespace App\Http\Controllers;

use App\Models\CertificateType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CertificateTypeController extends Controller
{
    public function index()
    {
        $certificateTypes = CertificateType::all();
        return Inertia::render('Documentation/CertificateTypes/Index', [
            'certificateTypes' => $certificateTypes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:certificate_types,name',
        ]);

        CertificateType::create($validated);

        return redirect()->route('certificate-types.index')->with('success', 'Tipo de certificado creado exitosamente.');
    }

    public function update(Request $request, CertificateType $certificateType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:certificate_types,name,' . $certificateType->id,
        ]);

        $certificateType->update($validated);

        return redirect()->route('certificate-types.index')->with('success', 'Tipo de certificado actualizado exitosamente.');
    }

    public function destroy(CertificateType $certificateType)
    {
        $certificateType->delete();

        return redirect()->route('certificate-types.index')->with('success', 'Tipo de certificado eliminado exitosamente.');
    }
}
