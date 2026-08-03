<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryPackaging;
use App\Services\Inventory\PackagingCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PackagingController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $filters = $request->only(['q', 'tipo', 'active']);

        $packagings = InventoryPackaging::query()
            ->when($filters['q'] ?? null, function ($query, $value) {
                $needle = trim((string) $value);
                $query->where(function ($inner) use ($needle): void {
                    $inner->where('codigo', 'like', '%'.$needle.'%')
                        ->orWhere('nombre', 'like', '%'.$needle.'%')
                        ->orWhere('descripcion', 'like', '%'.$needle.'%');
                });
            })
            ->when($filters['tipo'] ?? null, fn ($query, $value) => $query->where('tipo', $value))
            ->when(($filters['active'] ?? '') !== '', fn ($query) => $query->where('activo', (bool) $request->boolean('active')))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (InventoryPackaging $packaging) => [
                'id' => $packaging->id,
                'codigo' => $packaging->codigo,
                'nombre' => $packaging->nombre,
                'tipo' => $packaging->tipo,
                'peso_std' => $packaging->peso_std !== null ? (float) $packaging->peso_std : null,
                'tramo_sag_embalajes' => $packaging->tramo_sag_embalajes,
                'descripcion' => $packaging->descripcion,
                'altura' => $packaging->altura,
                'cantidad_cajas' => $packaging->cantidad_cajas !== null ? (float) $packaging->cantidad_cajas : null,
                'multiplicador' => $packaging->multiplicador !== null ? (float) $packaging->multiplicador : null,
                'activo' => (bool) $packaging->activo,
            ]);

        $types = InventoryPackaging::query()
            ->whereNotNull('tipo')
            ->distinct()
            ->orderBy('tipo')
            ->pluck('tipo')
            ->values();

        return Inertia::render('Inventory/Packagings/Index', [
            'packagings' => $packagings,
            'filters' => $filters,
            'types' => $types,
        ]);
    }

    public function update(Request $request, InventoryPackaging $packaging): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['nullable', 'string', 'max:20'],
            'peso_std' => ['nullable', 'numeric'],
            'tramo_sag_embalajes' => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'altura' => ['nullable', 'string', 'max:50'],
            'cantidad_cajas' => ['nullable', 'numeric'],
            'multiplicador' => ['nullable', 'numeric'],
            'activo' => ['boolean'],
        ]);

        $packaging->fill($data)->save();

        return back()->with('success', 'Embalaje actualizado.');
    }

    public function sync(Request $request, PackagingCatalogService $catalogService): RedirectResponse
    {
        $this->authorizeInventory($request);

        try {
            $summary = $catalogService->syncFromSqlsrv();
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', 'No fue posible importar embalajes desde SQL Server.');
        }

        return back()->with('success', "Embalajes importados. {$summary['created']} creados, {$summary['updated']} actualizados.");
    }
}
