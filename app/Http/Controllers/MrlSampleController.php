<?php

namespace App\Http\Controllers;

use App\Models\MrlSample;
use App\Models\User;
use App\Models\Especie;
use App\Models\Variedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MrlSampleController extends Controller
{
    public function index()
    {
        $mrlSamples = MrlSample::with(['user', 'especie', 'variedad'])->get();

        return Inertia::render('MrlSamples/Index', [
            'mrlSamples' => $mrlSamples,
        ]);
    }

    public function create()
    {
        $users = User::all();
        $especies = Especie::all();
        $variedades = Variedad::all();

        return Inertia::render('MrlSamples/Create', [
            'users' => $users,
            'especies' => $especies,
            'variedades' => $variedades,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'especie_id' => ['required', 'exists:especies,id'],
            'variedad_id' => ['required', 'exists:variedades,id'],
            'csg' => ['required', 'string', 'max:255'],
            'laboratory' => ['required', 'string', 'max:255'],
            'sampling_date' => ['required', 'date'],
            'result_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:2048'], // Add file validation
        ]);

        if ($request->hasFile('result_file')) {
            $filePath = $request->file('result_file')->store('mrl_results', 'public');
            $validatedData['result_file_path'] = $filePath;
        }

        MrlSample::create($validatedData);

        return redirect()->route('mrl-samples.index')->with('success', 'Muestra MRL creada exitosamente.');
    }

    public function getVariedadesByEspecie($especieId)
    {
        $variedades = Variedad::where('especie_id', $especieId)->get();
        return response()->json($variedades);
    }

    public function getCsgsByRut($rut)
    {
        $csgs = User::where('rut', $rut)
                    ->whereNotNull('csg')
                    ->pluck('csg')
                    ->unique()
                    ->values();

        return response()->json($csgs);
    }
}
