<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $services = Service::with('users', 'owner', 'phones', 'emails')->get();

        $search = $request->input('search');
        $availableUsersQuery = User::whereDoesntHave('services');

        if ($search) {
            $availableUsersQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $availableUsers = $availableUsersQuery->paginate(10)->withQueryString();

        return Inertia::render('Services/Index', [
            'services' => $services,
            'availableUsers' => $availableUsers,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Services/Create', [
            'users' => User::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'required|exists:users,id',
            'phones' => 'present|array',
            'phones.*' => 'nullable|string|max:255',
            'emails' => 'present|array',
            'emails.*' => 'nullable|email|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $service = Service::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'owner_id' => $validated['owner_id'],
            ]);

            if (!empty($validated['phones'])) {
                foreach ($validated['phones'] as $phone) {
                    if($phone) { // Ensure phone is not null or empty
                        $service->phones()->create(['phone' => $phone]);
                    }
                }
            }

            if (!empty($validated['emails'])) {
                foreach ($validated['emails'] as $email) {
                    if($email) { // Ensure email is not null or empty
                        $service->emails()->create(['email' => $email]);
                    }
                }
            }
        });

        return redirect()->route('services.index');
    }

    public function show(Service $service)
    {
        $service->load('users');
        $availableUsers = User::whereDoesntHave('services', function ($query) use ($service) {
            $query->where('service_id', $service->id);
        })->get();

        return Inertia::render('Services/Show', [
            'service' => $service,
            'availableUsers' => $availableUsers,
        ]);
    }

    public function edit(Service $service)
    {
        $service->load('owner', 'phones', 'emails');
        return Inertia::render('Services/Edit', [
            'service' => $service,
            'users' => User::all(),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'required|exists:users,id',
            'phones' => 'present|array',
            'phones.*' => 'nullable|string|max:255',
            'emails' => 'present|array',
            'emails.*' => 'nullable|email|max:255',
        ]);

        DB::transaction(function () use ($validated, $service) {
            $service->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'owner_id' => $validated['owner_id'],
            ]);

            // Sync phones
            $service->phones()->delete();
            if (!empty($validated['phones'])) {
                foreach ($validated['phones'] as $phone) {
                    if ($phone) {
                        $service->phones()->create(['phone' => $phone]);
                    }
                }
            }

            // Sync emails
            $service->emails()->delete();
            if (!empty($validated['emails'])) {
                foreach ($validated['emails'] as $email) {
                    if ($email) {
                        $service->emails()->create(['email' => $email]);
                    }
                }
            }
        });

        return redirect()->route('services.index');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return redirect()->route('services.index');
    }

    public function attachUser(Request $request, Service $service)
    {
        Log::info('Attach User Request:', $request->all());
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Check if the user is already attached to this service to prevent duplicate entry errors
        if (!$service->users()->where('user_id', $request->user_id)->exists()) {
            $service->users()->attach($request->user_id);
        }

        return redirect()->route('services.index', [
            'page' => $request->input('page'),
            'search' => $request->input('search'),
        ]);
    }

    public function detachUser(Service $service, User $user, Request $request)
    {
        Log::info('Detach User Request:', $request->all());
        $service->users()->detach($user->id);

        return redirect()->route('services.index', [
            'page' => $request->input('page'),
            'search' => $request->input('search'),
        ]);
    }

    public function getServiceProducers(Service $service, Request $request)
    {
        return response()->json([
            'test_message' => 'Data received successfully!',
        ]);
    }
}
