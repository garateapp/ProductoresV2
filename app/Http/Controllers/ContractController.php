<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    public function index()
    {
        $contracts = Contract::with('user')->get();

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
        ]);
    }

    public function create()
    {
        $producers = User::whereNotNull('idprod')->where('is_active', true)->get();

        return Inertia::render('Contracts/Create', [
            'producers' => $producers,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'contract_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:55120'],
            'fecha_contrato' => ['required', 'date'],
            'vencimiento' => ['required', 'date', 'after_or_equal:fecha_contrato'],
            'comision' => ['required', 'numeric'],
            'flete_a_huerto' => ['required', 'string'],
            'rebate' => ['required', 'boolean'],
            'bonificacion' => ['required', 'boolean'],
            'tarifa_premium' => ['required', 'boolean'],
            'comparativa' => ['nullable', 'string', 'max:1000'],
            'descuento_fruta_comercial' => ['required', 'boolean'],
        ]);

        if ($request->hasFile('contract_file')) {
            $filePath = $request->file('contract_file')->store('contracts', 'public');
            $validatedData['contract_file_path'] = $filePath;
        }

        Contract::create($validatedData);

        return redirect()->route('contracts.index')->with('success', 'Contrato creado exitosamente.');
    }

    public function edit(Contract $contract)
    {
        $producers = User::whereNotNull('idprod')->get();

        return Inertia::render('Contracts/Edit', [
            'contract' => $contract->load('user'),
            'producers' => $producers,
        ]);
    }

    public function update(Request $request, Contract $contract)
    {
        $validatedData = $request->validate([
            'contract_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:5120'],
            'fecha_contrato' => ['required', 'date'],
            'vencimiento' => ['required', 'date', 'after_or_equal:fecha_contrato'],
            'comision' => ['required', 'numeric'],
            'flete_a_huerto' => ['required', 'string'],
            'rebate' => ['required', 'boolean'],
            'bonificacion' => ['required', 'boolean'],
            'tarifa_premium' => ['required', 'boolean'],
            'comparativa' => ['nullable', 'string', 'max:1000'],
            'descuento_fruta_comercial' => ['required', 'boolean'],
        ]);

        if ($request->hasFile('contract_file')) {
            $filePath = $request->file('contract_file')->store('contracts', 'public');
            $validatedData['contract_file_path'] = $filePath;
        }

        // Enforce non-editable producer: keep original user_id
        $validatedData['user_id'] = $contract->user_id;
        $contract->update($validatedData);

        return redirect()->route('contracts.index')->with('success', 'Contrato actualizado exitosamente.');
    }

    public function destroy(Contract $contract)
    {
        if ($contract->contract_file_path) {
            Storage::disk('public')->delete($contract->contract_file_path);
        }

        $contract->delete();

        return redirect()->route('contracts.index')->with('success', 'Contrato eliminado exitosamente.');
    }
}
