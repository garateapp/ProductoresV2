<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class ProducerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::role('Productor');

        // Filtering
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('rut', 'like', '%' . $request->search . '%');
            });
        }

        // Sorting
        if ($request->has('sort_by') && $request->has('sort_order')) {
            $query->orderBy($request->sort_by, $request->sort_order);
        } else {
            $query->orderBy('name', 'asc'); // Default sort
        }

        // Pagination
        $producers = $query->paginate(10); // You can adjust the per-page value

        return Inertia::render('Producers/Index', [
            'producers' => $producers->through(function ($producer) {
                return [
                    'id' => $producer->id,
                    'name' => $producer->name,
                    'email' => $producer->email,
                    'rut' => $producer->rut,
                    'user' => $producer->user,
                    'idprod' => $producer->idprod,
                    'csg' => $producer->csg,
                    'emnotification' => $producer->emnotification,
                    'kilos_netos' => $producer->kilos_netos,
                    'comercial' => $producer->comercial,
                    'desecho' => $producer->desecho,
                    'merma' => $producer->merma,
                    'exp' => $producer->exp,
                    'predio' => $producer->predio,
                    'comuna' => $producer->comuna,
                    'provincia' => $producer->provincia,
                    'direccion' => $producer->direccion,
                    'antiguedad' => $producer->antiguedad,
                    'fitosanitario' => $producer->fitosanitario,
                    'certificaciones' => $producer->certificaciones,
                    'status' => $producer->status,
                    'enviomasivo' => $producer->enviomasivo,
                ];
            }),
            'filters' => $request->all(['search', 'sort_by', 'sort_order']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Producers/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'rut' => 'nullable|string|max:255',
            'user' => 'nullable|string|max:255',
            'idprod' => 'nullable|string|max:255',
            'csg' => 'nullable|string|max:255',
            'emnotification' => 'boolean',
            'kilos_netos' => 'nullable|integer',
            'comercial' => 'nullable|integer',
            'desecho' => 'nullable|integer',
            'merma' => 'nullable|integer',
            'exp' => 'nullable|integer',
            'predio' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'provincia' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'antiguedad' => 'nullable|integer',
            'fitosanitario' => 'nullable|string|max:255',
            'certificaciones' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'enviomasivo' => 'boolean',
        ]);

        $producer = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rut' => $request->rut,
            'user' => $request->user,
            'idprod' => $request->idprod,
            'csg' => $request->csg,
            'emnotification' => $request->emnotification,
            'kilos_netos' => $request->kilos_netos,
            'comercial' => $request->comercial,
            'desecho' => $request->desecho,
            'merma' => $request->merma,
            'exp' => $request->exp,
            'predio' => $request->predio,
            'comuna' => $request->comuna,
            'provincia' => $request->provincia,
            'direccion' => $request->direccion,
            'antiguedad' => $request->antiguedad,
            'fitosanitario' => $request->fitosanitario,
            'certificaciones' => $request->certificaciones,
            'status' => $request->status,
            'enviomasivo' => $request->enviomasivo,
        ]);

        $producer->assignRole('Productor');

        return redirect()->route('producers.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $producer)
    {
        $producer->load('agronomists');
        return Inertia::render('Producers/Edit', [
            'producer' => [
                'id' => $producer->id,
                'name' => $producer->name,
                'email' => $producer->email,
                'rut' => $producer->rut,
                'user' => $producer->user,
                'idprod' => $producer->idprod,
                'csg' => $producer->csg,
                'emnotification' => $producer->emnotification,
                'kilos_netos' => $producer->kilos_netos,
                'comercial' => $producer->comercial,
                'desecho' => $producer->desecho,
                'merma' => $producer->merma,
                'exp' => $producer->exp,
                'predio' => $producer->predio,
                'comuna' => $producer->comuna,
                'provincia' => $producer->provincia,
                'direccion' => $producer->direccion,
                'antiguedad' => $producer->antiguedad,
                'fitosanitario' => $producer->fitosanitario,
                'certificaciones' => $producer->certificaciones,
                'status' => $producer->status,
                'enviomasivo' => $producer->enviomasivo,
                'telefonos' => $producer->telefonos()->get(),
                'agronomists' => $producer->agronomists->map(function ($agronomist) {
                    return [
                        'id' => $agronomist->id,
                        'name' => $agronomist->name,
                        'email' => $agronomist->email,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $producer)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$producer->id,
            'rut' => 'nullable|string|max:255',
            'user' => 'nullable|string|max:255',
            'idprod' => 'nullable|string|max:255',
            'csg' => 'nullable|string|max:255',
            'emnotification' => 'boolean',
            'kilos_netos' => 'nullable|integer',
            'comercial' => 'nullable|integer',
            'desecho' => 'nullable|integer',
            'merma' => 'nullable|integer',
            'exp' => 'nullable|integer',
            'predio' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'provincia' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'antiguedad' => 'nullable|integer',
            'fitosanitario' => 'nullable|string|max:255',
            'certificaciones' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'enviomasivo' => 'boolean',
        ]);

        $producer->update($request->all());

        return redirect()->route('producers.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $producer)
    {
        $producer->delete();

        return redirect()->route('producers.index');
    }
}
