<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\GarcesPalletDefault;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GarcesPalletDefaultController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q']);

        $defaults = GarcesPalletDefault::query()
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('code_subsidiary', 'like', "%{$v}%")
                    ->orWhere('code_season', 'like', "%{$v}%");
            }))
            ->orderBy('code_subsidiary')
            ->orderBy('code_season')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (GarcesPalletDefault $d) => [
                'id' => $d->id,
                'code_subsidiary' => $d->code_subsidiary,
                'code_season' => $d->code_season,
                'subsidiary_garces' => $d->subsidiary_garces,
                'season_garces' => $d->season_garces,
                'tipo_tarja' => $d->tipo_tarja,
                'tipo_fruta' => $d->tipo_fruta,
                'tipo_pallet' => $d->tipo_pallet,
                'is_export' => $d->is_export,
                'activo' => $d->activo,
            ]);

        return Inertia::render('Integrations/Garces/Defaults/Index', [
            'defaults' => $defaults,
            'filters' => $filters,
            'isExportDefault' => GarcesPalletDefault::IS_EXPORT_DEFAULT,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        GarcesPalletDefault::updateOrCreate(
            [
                'code_subsidiary' => $data['code_subsidiary'],
                'code_season' => $data['code_season'],
            ],
            array_merge($data, ['activo' => $data['activo'] ?? true])
        );

        return back()->with('success', 'Default de pallet guardado.');
    }

    public function update(Request $request, GarcesPalletDefault $default)
    {
        $data = $this->validated($request);

        $default->update($data);

        return back()->with('success', 'Default actualizado.');
    }

    public function destroy(GarcesPalletDefault $default)
    {
        $default->delete();

        return back()->with('success', 'Default eliminado.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'code_subsidiary' => 'required|string|max:20',
            'code_season' => 'required|string|max:20',
            'subsidiary_garces' => 'nullable|string|max:20',
            'season_garces' => 'nullable|string|max:20',
            'tipo_tarja' => 'nullable|string|max:40',
            'tipo_fruta' => 'nullable|string|max:40',
            'tipo_pallet' => 'nullable|string|max:40',
            'is_export' => 'nullable|integer|in:0,1',
            'activo' => 'boolean',
        ]);
    }
}
