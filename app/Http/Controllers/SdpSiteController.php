<?php

namespace App\Http\Controllers;

use App\Models\SdpSite;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SdpSiteController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'active']);

        $query = SdpSite::query()->with(['csgUser' => function ($q) {
            $q->select('id', 'name', 'rut', 'csg');
        }]);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhereHas('csgUser', function ($q2) use ($search) {
                      $q2->where('csg', 'like', "%{$search}%")
                         ->orWhere('rut', 'like', "%{$search}%")
                         ->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $sites = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Sag/SdpSites/Index', [
            'sites' => $sites,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        // List of users that represent CSG (users with non-null CSG)
        $csgUsers = User::whereNotNull('csg')->select('id', 'name', 'rut', 'csg')->orderBy('name')->get();

        return Inertia::render('Sag/SdpSites/Create', [
            'csgUsers' => $csgUsers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'csg_user_id' => 'required|exists:users,id',
            'code' => 'nullable|string|max:100',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        SdpSite::create($validated);

        return redirect()->route('sdp-sites.index')->with('success', 'SDP creado correctamente.');
    }

    public function edit(SdpSite $sdp_site)
    {
        $csgUsers = User::whereNotNull('csg')->select('id', 'name', 'rut', 'csg')->orderBy('name')->get();

        return Inertia::render('Sag/SdpSites/Edit', [
            'site' => $sdp_site->load('csgUser:id,name,rut,csg'),
            'csgUsers' => $csgUsers,
        ]);
    }

    public function update(Request $request, SdpSite $sdp_site)
    {
        $validated = $request->validate([
            'csg_user_id' => 'required|exists:users,id',
            'code' => 'nullable|string|max:100',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $sdp_site->update($validated);

        return redirect()->route('sdp-sites.index')->with('success', 'SDP actualizado.');
    }

    public function destroy(SdpSite $sdp_site)
    {
        $sdp_site->delete();
        return redirect()->route('sdp-sites.index')->with('success', 'SDP eliminado.');
    }
}

