<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\Personal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonalController extends Controller
{
    use AuthorizesInventory;

    public function store(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);

        $request->merge([
            'nombre' => trim((string) $request->input('nombre')),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'cargo' => filled($request->input('cargo'))
                ? trim((string) $request->input('cargo'))
                : null,
        ]);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('personal', 'email'),
            ],
            'cargo' => ['nullable', 'string', 'max:150'],
        ]);

        $person = Personal::query()->create($data);

        return response()->json([
            'person' => [
                'id' => $person->id,
                'nombre' => $person->nombre,
                'email' => $person->email,
                'cargo' => $person->cargo,
            ],
        ], 201);
    }
}
