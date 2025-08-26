<?php

namespace App\Http\Controllers;

use App\Models\PhotoType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhotoTypeController extends Controller
{
    public function index()
    {
        $photoTypes = PhotoType::latest()->paginate(10);
        return Inertia::render('PhotoTypes/Index', ['photoTypes' => $photoTypes]);
    }

    public function create()
    {
        return Inertia::render('PhotoTypes/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:photo_types',
            'description' => 'nullable|string',
        ]);

        PhotoType::create($request->all());

        return redirect()->route('photo-types.index')->with('success', 'Tipo de foto creado exitosamente.');
    }

    public function edit(PhotoType $photoType)
    {
        return Inertia::render('PhotoTypes/Edit', ['photoType' => $photoType]);
    }

    public function update(Request $request, PhotoType $photoType)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:photo_types,name,'.$photoType->id,
            'description' => 'nullable|string',
        ]);

        $photoType->update($request->all());

        return redirect()->route('photo-types.index')->with('success', 'Tipo de foto actualizado exitosamente.');
    }

    public function destroy(PhotoType $photoType)
    {
        $photoType->delete();

        return redirect()->route('photo-types.index')->with('success', 'Tipo de foto eliminado exitosamente.');
    }
}