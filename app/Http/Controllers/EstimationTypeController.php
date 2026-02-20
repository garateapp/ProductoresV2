<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresEstimationsAccess;
use App\Http\Resources\EstimationTypeResource;
use App\Models\EstimationType;
use App\Models\EstimationVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EstimationTypeController extends Controller
{
    use EnsuresEstimationsAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureEstimationsAccess($request);

        $types = EstimationType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return EstimationTypeResource::collection($types);
    }

    public function store(Request $request)
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'alpha_dash', 'unique:estimation_types,code'],
            'name' => ['required', 'string', 'max:80', 'unique:estimation_types,name'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $type = EstimationType::create([
            'code' => strtolower($data['code']),
            'name' => trim($data['name']),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($request->expectsJson()) {
            return new EstimationTypeResource($type);
        }

        return back()->with('success', 'Tipo creado.');
    }

    public function update(Request $request, EstimationType $estimation_type)
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'alpha_dash', 'unique:estimation_types,code,'.$estimation_type->id],
            'name' => ['required', 'string', 'max:80', 'unique:estimation_types,name,'.$estimation_type->id],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $estimation_type->update([
            'code' => strtolower($data['code']),
            'name' => trim($data['name']),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($request->expectsJson()) {
            return new EstimationTypeResource($estimation_type);
        }

        return back()->with('success', 'Tipo actualizado.');
    }

    public function destroy(Request $request, EstimationType $estimation_type)
    {
        $this->ensureEstimationsAccess($request);

        $isUsed = EstimationVersion::query()
            ->where('type', $estimation_type->code)
            ->exists();

        if ($isUsed) {
            $message = 'No se puede eliminar el tipo porque tiene versiones asociadas.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['type' => $message]);
        }

        $estimation_type->delete();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('success', 'Tipo eliminado.');
    }
}
