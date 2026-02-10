<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresEstimationsAccess;
use App\Http\Resources\EstimationStatusResource;
use App\Models\EstimationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EstimationStatusController extends Controller
{
    use EnsuresEstimationsAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureEstimationsAccess($request);

        $statuses = EstimationStatus::orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return EstimationStatusResource::collection($statuses);
    }

    public function store(Request $request)
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:estimation_statuses,name'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $status = EstimationStatus::create($data);

        if ($request->expectsJson()) {
            return new EstimationStatusResource($status);
        }

        return back()->with('success', 'Status creado.');
    }

    public function update(Request $request, EstimationStatus $estimation_status)
    {
        $this->ensureEstimationsAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:estimation_statuses,name,'.$estimation_status->id],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $estimation_status->update($data);

        if ($request->expectsJson()) {
            return new EstimationStatusResource($estimation_status);
        }

        return back()->with('success', 'Status actualizado.');
    }

    public function destroy(Request $request, EstimationStatus $estimation_status)
    {
        $this->ensureEstimationsAccess($request);

        $estimation_status->delete();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('success', 'Status eliminado.');
    }
}
