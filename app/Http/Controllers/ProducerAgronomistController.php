<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProducerAgronomistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $agronomists = User::role('Agronomo')->get();
        return response()->json($agronomists);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'producer_id' => 'required|exists:users,id',
            'agronomist_id' => 'required|exists:users,id',
        ]);

        // Check if the relationship already exists
        $exists = DB::table('campo_staff')
                    ->where('user_id', $request->producer_id)
                    ->where('agronomo_id', $request->agronomist_id)
                    ->exists();

        if (!$exists) {
            DB::table('campo_staff')->insert([
                'user_id' => $request->producer_id,
                'agronomo_id' => $request->agronomist_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Agronomist attached successfully.');
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
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'producer_id' => 'required|exists:users,id',
            'agronomist_id' => 'required|exists:users,id',
        ]);

        DB::table('campo_staff')
            ->where('user_id', $request->producer_id)
            ->where('agronomo_id', $request->agronomist_id)
            ->delete();

        return back()->with('success', 'Agronomist detached successfully.');
    }
}
