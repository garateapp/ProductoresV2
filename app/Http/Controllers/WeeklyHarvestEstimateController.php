<?php

namespace App\Http\Controllers;

use App\Models\WeeklyHarvestEstimate;
use App\Models\Especie;
use App\Models\Variedad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
class WeeklyHarvestEstimateController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['season_code','user_id','especie_id','variedad_id','iso_year','iso_week','status']);

        $query = WeeklyHarvestEstimate::query()
            ->with(['producer','agronomist','especie','variedad'])
            ->when($request->filled('season_code'), fn($q) => $q->where('season_code', $request->season_code))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('especie_id'), fn($q) => $q->where('especie_id', $request->especie_id))
            ->when($request->filled('variedad_id'), fn($q) => $q->where('variedad_id', $request->variedad_id))
            ->when($request->filled('iso_year'), fn($q) => $q->where('iso_year', $request->iso_year))
            ->when($request->filled('iso_week'), fn($q) => $q->where('iso_week', $request->iso_week))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('iso_year')->orderByDesc('iso_week');

        $estimates = $query->paginate(15)->through(function($e){
            return [
                'id' => $e->id,
                'producer' => $e->producer?->name,
                'producer_id' => $e->user_id,
                'agronomist' => $e->agronomist?->name,
                'agronomist_id' => $e->agronomist_id,
                'especie' => $e->especie?->name,
                'especie_id' => $e->especie_id,
                'variedad' => $e->variedad?->name,
                'variedad_id' => $e->variedad_id,
                'tipo_cereza' => $e->tipo_cereza,
                'acopio' => (bool) $e->acopio,
                'radio_mosca' => (bool) $e->radio_mosca,
                'corea_greenex' => (bool) $e->corea_greenex,
                'season_code' => $e->season_code,
                'iso_year' => $e->iso_year,
                'iso_week' => $e->iso_week,
                'predio' => $e->predio,
                'block' => $e->block,
                'estimated_kilos' => $e->estimated_kilos,
                'status' => $e->status,
            ];
        });

        $especies = Especie::with('variedads')->get(['id','name']);
        $producers = User::role('Productor')->get(['id','name','rut']);

        return Inertia::render('WeeklyHarvestEstimates/Index', [
            'estimates' => $estimates,
            'especies' => $especies,
            'producers' => $producers,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $especies = Especie::with('variedads')->get(['id','name']);
        $producers = User::role('Productor')->get(['id','name','rut']);
        return Inertia::render('WeeklyHarvestEstimates/Create', [
            'especies' => $especies,
            'producers' => $producers,
            'defaults' => [
                'season_code' => '',
                'iso_year' => (int) now()->isoFormat('GGGG'),
                'iso_week' => (int) now()->isoWeek,
                'status' => 'draft',
                'source' => 'manual',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'agronomist_id' => 'nullable|exists:users,id',
            'especie_id' => 'required|exists:especies,id',
            'variedad_id' => 'nullable|exists:variedads,id',
            'season_code' => 'required|string|max:32',
            'iso_year' => 'required|integer|min:2000|max:2100',
            'iso_week' => 'required|integer|min:1|max:53',
            'predio' => 'nullable|string|max:191',
            'block' => 'nullable|string|max:191',
            'estimated_kilos' => 'required|numeric|min:0',
            'estimated_bins' => 'nullable|numeric|min:0',
            'estimated_boxes' => 'nullable|integer|min:0',
            'confidence_pct' => 'nullable|integer|between:0,100',
            'status' => 'nullable|in:draft,confirmed,final',
            'source' => 'nullable|string|max:32',
            'notes' => 'nullable|string',
            'acopio' => 'nullable|boolean',
            'radio_mosca' => 'nullable|boolean',
            'corea_greenex' => 'nullable|boolean',
            'tipo_cereza' => 'nullable|string|max:32',
        ]);

        if (!empty($data['agronomist_id'])) {
            $exists = DB::table('campo_staff')
                ->where('user_id', $data['user_id'])
                ->where('agronomo_id', $data['agronomist_id'])
                ->exists();
            abort_unless($exists, 422, 'El agrónomo no está vinculado al productor.');
        }

        $week = Carbon::now()->setISODate((int)$data['iso_year'], (int)$data['iso_week']);
        $data['week_start_date'] = $week->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $data['week_end_date'] = $week->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $data['created_by'] = $request->user()->id ?? null;
        $data['updated_by'] = $request->user()->id ?? null;

        WeeklyHarvestEstimate::create($data);

        return redirect()->route('weekly-harvest-estimates.index')->with('success', 'Estimación creada.');
    }

    public function edit(WeeklyHarvestEstimate $weekly_harvest_estimate)
    {
        $weekly_harvest_estimate->load(['producer','agronomist','especie','variedad']);
        $especies = Especie::with('variedads')->get(['id','name']);
        $producers = User::role('Productor')->get(['id','name','rut']);

        return Inertia::render('WeeklyHarvestEstimates/Edit', [
            'estimate' => $weekly_harvest_estimate,
            'especies' => $especies,
            'producers' => $producers,
        ]);
    }

    public function update(Request $request, WeeklyHarvestEstimate $weekly_harvest_estimate)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'agronomist_id' => 'nullable|exists:users,id',
            'especie_id' => 'required|exists:especies,id',
            'variedad_id' => 'nullable|exists:variedads,id',
            'season_code' => 'required|string|max:32',
            'iso_year' => 'required|integer|min:2000|max:2100',
            'iso_week' => 'required|integer|min:1|max:53',
            'predio' => 'nullable|string|max:191',
            'block' => 'nullable|string|max:191',
            'estimated_kilos' => 'required|numeric|min:0',
            'estimated_bins' => 'nullable|numeric|min:0',
            'estimated_boxes' => 'nullable|integer|min:0',
            'confidence_pct' => 'nullable|integer|between:0,100',
            'status' => 'nullable|in:draft,confirmed,final',
            'source' => 'nullable|string|max:32',
            'notes' => 'nullable|string',
            'acopio' => 'nullable|boolean',
            'radio_mosca' => 'nullable|boolean',
            'corea_greenex' => 'nullable|boolean',
            'tipo_cereza' => 'nullable|string|max:32',
        ]);

        if (!empty($data['agronomist_id'])) {
            $exists = DB::table('campo_staff')
                ->where('user_id', $data['user_id'])
                ->where('agronomo_id', $data['agronomist_id'])
                ->exists();
            abort_unless($exists, 422, 'El agrónomo no está vinculado al productor.');
        }

        $week = Carbon::now()->setISODate((int)$data['iso_year'], (int)$data['iso_week']);
        $data['week_start_date'] = $week->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $data['week_end_date'] = $week->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
        $data['updated_by'] = $request->user()->id ?? null;

        $weekly_harvest_estimate->update($data);
        return redirect()->route('weekly-harvest-estimates.index')->with('success', 'Estimación actualizada.');
    }

    public function destroy(WeeklyHarvestEstimate $weekly_harvest_estimate)
    {
        $weekly_harvest_estimate->delete();
        return back()->with('success', 'Estimación eliminada.');
    }

    // API helper: agronomists assigned to a producer (via campo_staff)
    public function getProducerAgronomists(User $producer)
    {
        $rows = DB::table('campo_staff')
            ->join('users', 'users.id', '=', 'campo_staff.agronomo_id')
            ->where('campo_staff.user_id', $producer->id)
            ->select('users.id','users.name','users.email')
            ->get();
        return response()->json($rows);
    }

    // Import from Excel (stub that reads and upserts)
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'season_code' => 'required|string|max:32',
            'especie_id' => 'required|exists:especies,id',
            'source' => 'nullable|string|max:32',
        ]);

        $file = $request->file('file');
        if (!$file->isValid()) {
            return back()->withErrors(['file' => 'Archivo inválido']);
        }

        $sheets = \Maatwebsite\Excel\Facades\Excel::toCollection(null, $file);
        $collection = $sheets->first() ?? collect();
        $header = [];
        $imported = 0;
        // Expect wide table with weekly columns named as numbers 44..52,1..5
        // First, detect week columns from header (numeric strings)
        $weekHeaders = [];
        foreach ($collection as $rowIndex => $row) {
            $rowArray = is_array($row) ? $row : $row->toArray();
            if ($rowIndex === 0) { // assume first row header
                $header = array_map(function($h){ return is_string($h) ? trim($h) : $h; }, $rowArray);
                foreach ($header as $h) {
                    $hs = is_string($h) ? trim($h) : '';
                    if ($hs !== '' && is_numeric($hs)) { $weekHeaders[] = (int) $hs; }
                }
                continue;
            }
            if (empty($header)) { continue; }
            if (count($header) !== count($rowArray)) { continue; }
            $data = @array_combine($header, $rowArray);
            if (!$data || !is_array($data)) { continue; }

            // Identify producer by 'GRUPO' or 'RAZON SOCIAL'
            $grupo = trim((string)($data['GRUPO'] ?? $data['Grupo'] ?? $data['grupo'] ?? ''));
            $razon = trim((string)($data['RAZON SOCIAL'] ?? $data['Razón Social'] ?? $data['razon_social'] ?? ''));
            $producer = null;
            if ($grupo !== '') {
                // try match by user name
                $producer = User::whereRaw('LOWER(name) = ?', [mb_strtolower($grupo)])->first();
                if (!$producer) {
                    // try by group mapping
                    $groupModel = \App\Models\ProducerGroup::whereRaw('LOWER(name) = ?', [mb_strtolower($grupo)])->first();
                    if ($groupModel) {
                        $groupModel->loadMissing('producers');
                        if ($razon !== '') {
                            $producer = $groupModel->producers->firstWhere(function($u) use ($razon){
                                return mb_strtolower($u->name) === mb_strtolower($razon);
                            });
                        }
                        if (!$producer && $groupModel->producers->count() === 1) {
                            $producer = $groupModel->producers->first();
                        }
                    }
                }
            }
            if (!$producer && $razon !== '') {
                $producer = User::whereRaw('LOWER(name) = ?', [mb_strtolower($razon)])->first();
            }
            if (!$producer) { continue; }

            // Agronomist by name (optional)
            $agroRaw = trim((string)($data['Agronomo'] ?? $data['AGRÓNOMO'] ?? $data['Agrónomo'] ?? ''));
            $agroName = trim(preg_replace('/^\d+\s*/', '', $agroRaw));
            $agronomist = $agroName ? User::role('Agronomo')->whereRaw('LOWER(name) = ?', [mb_strtolower($agroName)])->first() : null;

            // Especie from input select
            $especie = Especie::find($request->integer('especie_id'));
            if (!$especie) { continue; }

            // Variedad by name (optional)
            $variedadName = trim((string)($data['VARIEDAD'] ?? $data['Variedad'] ?? $data['variedad'] ?? ''));
            $variedad = $variedadName !== '' ? Variedad::whereRaw('LOWER(name) = ?', [mb_strtolower($variedadName)])->first() : null;

            // Season years from season_code (e.g., T25-26)
            [$yearA, $yearB] = $this->parseSeasonYears($request->season_code);
            if (!$yearA || !$yearB) { continue; }

            // Parse per-row meta fields
            $acopio = $this->parseYesNo($data['ACOPIO'] ?? $data['Acopio'] ?? $data['acopio'] ?? null);
            $radioMosca = $this->parseYesNo($data['RADIO MOSCA'] ?? $data['Radio Mosca'] ?? $data['radio_mosca'] ?? null);
            $coreaGreenex = $this->parseYesNo($data['COREA GREENEX'] ?? $data['Corea Greenex'] ?? $data['corea_greenex'] ?? null);
            $tipoCereza = trim((string)($data['TIPO CEREZA'] ?? $data['Tipo Cereza'] ?? $data['tipo_cereza'] ?? '')) ?: null;

            foreach ($weekHeaders as $w) {
                $col = (string)$w;
                if (!array_key_exists($col, $data)) { continue; }
                $raw = (string)$data[$col];
                $kilos = $this->parseNumber($raw);
                if ($kilos <= 0) { continue; }
                $iso_year = ($w >= 44) ? $yearA : $yearB;
                $week = Carbon::now()->setISODate($iso_year, $w);
                $payload = [
                    'user_id' => $producer->id,
                    'agronomist_id' => $agronomist?->id,
                    'especie_id' => $especie->id,
                    'variedad_id' => $variedad?->id,
                    'season_code' => $request->season_code,
                    'iso_year' => $iso_year,
                    'iso_week' => $w,
                    'week_start_date' => $week->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                    'week_end_date' => $week->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
                    'predio' => null,
                    'block' => null,
                    'estimated_kilos' => $kilos,
                    'acopio' => $acopio,
                    'radio_mosca' => $radioMosca,
                    'corea_greenex' => $coreaGreenex,
                    'tipo_cereza' => $tipoCereza,
                    'status' => 'draft',
                    'source' => $request->input('source', 'import_xlsx'),
                    'created_by' => $request->user()->id ?? null,
                    'updated_by' => $request->user()->id ?? null,
                ];

                WeeklyHarvestEstimate::updateOrCreate([
                    'user_id' => $payload['user_id'],
                    'especie_id' => $payload['especie_id'],
                    'variedad_id' => $payload['variedad_id'],
                    'season_code' => $payload['season_code'],
                    'iso_year' => $payload['iso_year'],
                    'iso_week' => $payload['iso_week'],
                    'predio' => $payload['predio'],
                    'block' => $payload['block'],
                ], $payload);
                $imported++;
            }
        }

        return back()->with('success', "Importadas: {$imported}");
    }

    private function parseSeasonYears(string $seasonCode): array
    {
        $s = trim($seasonCode);
        $s = strtoupper($s);
        $s = ltrim($s, 'T');
        if (!str_contains($s, '-')) { return [null, null]; }
        [$a, $b] = array_map('trim', explode('-', $s, 2));
        if (!is_numeric($a) || !is_numeric($b)) { return [null, null]; }
        $yearA = (int) ('20' . $a);
        $yearB = (int) ('20' . $b);
        return [$yearA, $yearB];
    }

    private function parseNumber($value): float
    {
        if ($value === null) return 0.0;
        $s = (string)$value;
        // Remove thousand separators and normalize decimal comma
        $s = str_replace(['.',' '], ['', ''], $s);
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float)$s : 0.0;
    }

    private function parseYesNo($value): ?bool
    {
        if ($value === null) return null;
        $s = mb_strtoupper(trim((string)$value));
        if ($s === 'SI' || $s === 'SÍ' || $s === 'YES' || $s === 'Y' || $s === '1') return true;
        if ($s === 'NO' || $s === 'N' || $s === '0') return false;
        return null;
    }
}
