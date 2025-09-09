<?php

namespace App\Http\Controllers;

use App\Models\ProducerGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProducerGroupController extends Controller
{
    public function index()
    {
        $groups = ProducerGroup::with(['producers:id,name'])->orderBy('name')->get();
        $producers = User::role('Productor')->orderBy('name')->get(['id','name','rut']);
        return Inertia::render('ProducerGroups/Index', [
            'groups' => $groups,
            'producers' => $producers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:producer_groups,name',
            'description' => 'nullable|string',
        ]);
        ProducerGroup::create($data);
        return back()->with('success', 'Grupo creado');
    }

    public function edit(ProducerGroup $producer_group)
    {
        $producer_group->load('producers:id,name');
        $producers = User::role('Productor')->orderBy('name')->get(['id','name','rut']);
        return Inertia::render('ProducerGroups/Edit', [
            'group' => $producer_group,
            'producers' => $producers,
        ]);
    }

    public function update(Request $request, ProducerGroup $producer_group)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:producer_groups,name,' . $producer_group->id,
            'description' => 'nullable|string',
            'producer_ids' => 'array',
            'producer_ids.*' => 'integer|exists:users,id',
        ]);

        $producer_group->update($data);

        if ($request->has('producer_ids')) {
            $producer_group->producers()->sync($data['producer_ids']);
        }

        return redirect()->route('producer-groups.index')->with('success', 'Grupo actualizado');
    }

    public function destroy(ProducerGroup $producer_group)
    {
        $producer_group->delete();
        return back()->with('success', 'Grupo eliminado');
    }
}

