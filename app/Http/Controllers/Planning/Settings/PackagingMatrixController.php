<?php

namespace App\Http\Controllers\Planning\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Models\Especie;
use App\Models\PackagingMatrixRule;
use App\Services\Planning\PackagingRepositorySqlsrv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PackagingMatrixController extends Controller
{
    use AuthorizesPlanning;

    private const CHERRIES_CALIBRES = ['L','LD','XL','XLD','J','JD','2J','2JD','3J','3JD','4J','4JD','5J','5JD','6J','6JD','7J','7JD'];
    private const CAROZOS_CALIBRES = [28, 30, 32, 36, 40, 44, 48, 52, 56, 60, 66, 72, 78, 88, 98, 108, 120];

    public function __construct(private readonly PackagingRepositorySqlsrv $packagingRepository)
    {
    }

    public function index(Request $request)
    {
        $this->authorizePlanning($request);

        $filters = $request->validate([
            'matrix' => ['nullable', 'string', 'in:carozos,cherries'],
            'especie' => ['nullable', 'string', 'max:80'],
            'destino' => ['nullable', 'string', 'max:60'],
            'q' => ['nullable', 'string', 'max:120'],
            'only_active' => ['nullable', 'boolean'],
        ]);

        $matrix = (string) ($filters['matrix'] ?? 'carozos');

        $query = PackagingMatrixRule::query()
            ->where('matrix', $matrix)
            ->orderBy('priority')
            ->orderBy('id');

        if (! empty($filters['especie'])) {
            $query->where('especie', (string) $filters['especie']);
        }
        if (! empty($filters['destino'])) {
            $query->where('destino', (string) $filters['destino']);
        }

        $needle = trim((string) ($filters['q'] ?? ''));
        if ($needle !== '') {
            $query->where(function ($q) use ($needle) {
                $q->where('c_item', 'like', '%'.$needle.'%')
                    ->orWhere('variedad', 'like', '%'.$needle.'%')
                    ->orWhere('color', 'like', '%'.$needle.'%')
                    ->orWhere('nota', 'like', '%'.$needle.'%')
                    ->orWhere('desc_embalaje', 'like', '%'.$needle.'%');
            });
        }

        $onlyActive = array_key_exists('only_active', $filters) ? (bool) $filters['only_active'] : true;
        if ($onlyActive) {
            $query->where('activo', true);
        }

        $rules = $query->get();

        $codes = $rules->pluck('c_item')->filter()->unique()->values()->all();
        $catalog = $this->packagingRepository->getPackagingsByCodes($codes);

        $rulesOut = $rules->map(function (PackagingMatrixRule $r) use ($catalog) {
            $c = $catalog[$r->c_item] ?? null;
            return [
                'id' => $r->id,
                'especie' => $r->especie,
                'destino' => $r->destino,
                'nota' => $r->nota,
                'variedad' => $r->variedad,
                'color' => $r->color,
                'require_sdp' => (bool) $r->require_sdp,
                'c_item' => $r->c_item,
                'desc_embalaje' => $c['n_item'] ?? $r->desc_embalaje,
                'peso_caja' => $r->peso_caja,
                'allowed_calibres' => $r->allowed_calibres ?? [],
                'calibres_note' => $r->calibres_note,
                'sobre_calibre_note' => $r->sobre_calibre_note,
                'priority' => $r->priority,
                'activo' => (bool) $r->activo,
                'cp2_cajas_por_pallet' => $c['cp2_cajas_por_pallet'] ?? null,
            ];
        })->values();

        // Especies disponibles:
        // - Preferimos catálogo maestro (tabla `especies`)
        // - Pero también unimos con las especies ya usadas en reglas (por si el catálogo está incompleto
        //   o hay diferencias de nomenclatura/caso en datos históricos).
        $master = Especie::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->values()
            ->all();

        $fromRules = PackagingMatrixRule::query()
            ->where('matrix', $matrix)
            ->whereNotNull('especie')
            ->where('especie', '!=', '')
            ->distinct()
            ->orderBy('especie')
            ->pluck('especie')
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->values()
            ->all();

        // También unimos con SQLSRV (existencias) y con estimaciones, porque en la práctica
        // el catálogo maestro puede estar incompleto/desactualizado.
        $fromSqlsrv = [];
        try {
            $fromSqlsrv = Cache::remember('planning:packaging-matrix:especies:sqlsrv:v2', now()->addHours(4), function () {
                return DB::connection('sqlsrv')
                    ->table('V_PKG_Stock_Inventario')
                    ->select('n_especie')
                    ->whereNotNull('n_especie')
                    ->where('n_especie', '!=', '')
                    ->distinct()
                    ->orderBy('n_especie')
                    ->pluck('n_especie')
                    ->map(fn ($v) => trim((string) $v))
                    ->filter(fn ($v) => $v !== '')
                    ->values()
                    ->all();
            });
        } catch (\Throwable $e) {
            $fromSqlsrv = [];
        }

        $fromEstimations = [];
        try {
            $fromEstimations = Cache::remember('planning:packaging-matrix:especies:estimations:v2', now()->addHours(4), function () {
                return DB::table('estimation_biweekly_rows')
                    ->select('especie')
                    ->whereNotNull('especie')
                    ->where('especie', '!=', '')
                    ->distinct()
                    ->orderBy('especie')
                    ->pluck('especie')
                    ->map(fn ($v) => trim((string) $v))
                    ->filter(fn ($v) => $v !== '')
                    ->values()
                    ->all();
            });
        } catch (\Throwable $e) {
            $fromEstimations = [];
        }

        $fromProcesses = [];
        try {
            $fromProcesses = Cache::remember('planning:packaging-matrix:especies:processes:v1', now()->addHours(4), function () {
                return DB::table('packing_processes')
                    ->select('especie')
                    ->whereNotNull('especie')
                    ->where('especie', '!=', '')
                    ->distinct()
                    ->orderBy('especie')
                    ->pluck('especie')
                    ->map(fn ($v) => trim((string) $v))
                    ->filter(fn ($v) => $v !== '')
                    ->values()
                    ->all();
            });
        } catch (\Throwable $e) {
            $fromProcesses = [];
        }

        $map = [];
        foreach ($master as $name) {
            $k = mb_strtolower($name);
            if (! isset($map[$k])) {
                $map[$k] = $name;
            }
        }
        foreach ($fromRules as $name) {
            $k = mb_strtolower($name);
            if (! isset($map[$k])) {
                $map[$k] = $name;
            }
        }
        foreach ($fromSqlsrv as $name) {
            $k = mb_strtolower($name);
            if (! isset($map[$k])) {
                $map[$k] = $name;
            }
        }
        foreach ($fromEstimations as $name) {
            $k = mb_strtolower($name);
            if (! isset($map[$k])) {
                $map[$k] = $name;
            }
        }
        foreach ($fromProcesses as $name) {
            $k = mb_strtolower($name);
            if (! isset($map[$k])) {
                $map[$k] = $name;
            }
        }

        // Base mínima visible por matriz para asegurar opciones operativas.
        $requiredSpecies = $matrix === 'carozos'
            ? ['Nectarines', 'Plums', 'Peaches']
            : ['Cherries'];
        foreach ($requiredSpecies as $name) {
            $k = mb_strtolower($name);
            if (! isset($map[$k])) {
                $map[$k] = $name;
            }
        }

        $especies = array_values($map);
        usort($especies, fn ($a, $b) => strcasecmp((string) $a, (string) $b));
        $destinos = PackagingMatrixRule::query()
            ->where('matrix', $matrix)
            ->whereNotNull('destino')
            ->where('destino', '!=', '')
            ->distinct()
            ->orderBy('destino')
            ->pluck('destino')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        $calibres = $matrix === 'cherries' ? self::CHERRIES_CALIBRES : self::CAROZOS_CALIBRES;

        return Inertia::render('Planning/Settings/PackagingMatrix', [
            'rules' => $rulesOut,
            'especies' => $especies,
            'destinos' => $destinos,
            'calibres' => $calibres,
            'filters' => [
                'matrix' => $matrix,
                'especie' => $filters['especie'] ?? '',
                'destino' => $filters['destino'] ?? '',
                'q' => $filters['q'] ?? '',
                'only_active' => $onlyActive ? 1 : 0,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePlanning($request);

        $data = $this->validatedRule($request);
        $data['matrix'] = (string) ($request->input('matrix') ?: 'carozos');

        $catalog = $this->packagingRepository->getPackagingByCode((string) $data['c_item']);
        if (! $catalog) {
            return back()->with('error', 'El código de embalaje no existe en el catálogo (SQLSRV).');
        }
        if (empty($data['desc_embalaje'])) {
            $data['desc_embalaje'] = (string) ($catalog['n_item'] ?? '');
        }

        if (! isset($data['priority']) || $data['priority'] === null) {
            $matrix = (string) ($data['matrix'] ?? 'carozos');
            if (! in_array($matrix, ['carozos', 'cherries'], true)) {
                $matrix = 'carozos';
            }
            $max = (int) PackagingMatrixRule::query()->where('matrix', $matrix)->max('priority');
            $data['priority'] = max(0, $max) + 10;
        }

        PackagingMatrixRule::create($data);

        return back()->with('success', 'Regla creada.');
    }

    public function update(Request $request, PackagingMatrixRule $rule)
    {
        $this->authorizePlanning($request);

        $data = $this->validatedRule($request);

        $catalog = $this->packagingRepository->getPackagingByCode((string) $data['c_item']);
        if (! $catalog) {
            return back()->with('error', 'El código de embalaje no existe en el catálogo (SQLSRV).');
        }
        if (empty($data['desc_embalaje'])) {
            $data['desc_embalaje'] = (string) ($catalog['n_item'] ?? '');
        }

        $rule->forceFill($data)->save();

        return back()->with('success', 'Regla actualizada.');
    }

    public function destroy(Request $request, PackagingMatrixRule $rule)
    {
        $this->authorizePlanning($request);
        $rule->delete();
        return back()->with('success', 'Regla eliminada.');
    }

    public function importCsv(Request $request)
    {
        $this->authorizePlanning($request);

        $matrix = (string) ($request->input('matrix') ?: 'carozos');
        if ($matrix !== 'carozos') {
            return back()->with('error', 'La importación desde CSV actualmente está disponible solo para carozos.');
        }

        $cfg = (array) config('planning.packaging_matrix.carozos', []);
        $storagePath = (string) ($cfg['storage_path'] ?? '');
        $fallbackPath = (string) ($cfg['fallback_path'] ?? '');
        $path = $storagePath !== '' && File::exists($storagePath) ? $storagePath : $fallbackPath;
        if ($path === '' || ! File::exists($path)) {
            return back()->with('error', 'No se encontró el CSV de la matriz para importar.');
        }

        $lines = collect(File::lines($path))
            ->map(fn ($l) => trim((string) $l))
            ->filter(fn ($l) => $l !== '' && ! preg_match('/^;+\$/', $l))
            ->values();
        if ($lines->count() < 2) {
            return back()->with('error', 'El CSV no tiene datos para importar.');
        }

        $header = str_getcsv((string) $lines[0], ';');
        $calibreCols = array_values(array_filter($header, fn ($h) => preg_match('/^\\d{2,3}$/', (string) $h)));

        $rows = [];
        foreach ($lines->slice(1)->values() as $i => $line) {
            $data = str_getcsv((string) $line, ';');
            $assoc = [];
            foreach ($header as $idx => $h) {
                $assoc[(string) $h] = $data[$idx] ?? null;
            }

            $allowed = [];
            foreach ($calibreCols as $c) {
                $val = trim((string) ($assoc[$c] ?? ''));
                if ($val !== '' && mb_strtoupper($val) === 'X') {
                    $allowed[] = (int) $c;
                }
            }

            $color = (string) ($assoc['Color'] ?? '');
            $requireSdp = Str::contains(Str::upper(Str::ascii($color)), 'SDP');

            $sobre = trim((string) ($assoc['Sobre Calibre'] ?? ''));
            $mix = trim((string) ($assoc['MIX'] ?? ''));
            if ($sobre === '' && $mix !== '' && mb_strtoupper($mix) !== 'X') {
                $sobre = $mix;
            }

            $peso = trim((string) ($assoc['Peso Cja'] ?? ''));
            $peso = str_replace(',', '.', $peso);
            $pesoVal = is_numeric($peso) ? (float) $peso : null;

            $rows[] = [
                'matrix' => 'carozos',
                'especie' => trim((string) ($assoc['especie'] ?? '')),
                'destino' => trim((string) ($assoc['Destino'] ?? '')) ?: null,
                'nota' => trim((string) ($assoc['Nota'] ?? '')) ?: null,
                'variedad' => trim((string) ($assoc['Variedad'] ?? '')) ?: null,
                'color' => trim((string) ($assoc['Color'] ?? '')) ?: null,
                'require_sdp' => $requireSdp,
                'c_item' => trim((string) ($assoc['Embalaje'] ?? '')),
                'desc_embalaje' => trim((string) ($assoc['Desc Embalaje'] ?? '')) ?: null,
                'peso_caja' => $pesoVal,
                'allowed_calibres' => $allowed,
                'calibres_note' => trim((string) ($assoc['Calibres'] ?? '')) ?: null,
                'sobre_calibre_note' => $sobre ?: null,
                'priority' => ($i + 1) * 10,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        PackagingMatrixRule::query()->where('matrix', $matrix)->delete();
        if (! empty($rows)) {
            // `insert()` no aplica casts del modelo; el JSON debe ir como string.
            $rows = array_map(function (array $row) {
                $row['allowed_calibres'] = json_encode($row['allowed_calibres'] ?? [], JSON_UNESCAPED_UNICODE);
                $row['created_at'] = (string) ($row['created_at'] ?? now());
                $row['updated_at'] = (string) ($row['updated_at'] ?? now());
                return $row;
            }, $rows);
            PackagingMatrixRule::insert($rows);
        }

        return back()->with('success', 'Importación completa. La matriz ahora se mantiene desde la aplicación.');
    }

    public function importUpload(Request $request)
    {
        $this->authorizePlanning($request);

        $matrix = (string) ($request->input('matrix') ?: 'carozos');
        if (! in_array($matrix, ['carozos', 'cherries'], true)) {
            $matrix = 'carozos';
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,txt'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['file'];
        $content = (string) File::get($file->getRealPath());
        if ($content !== '' && function_exists('mb_check_encoding') && ! mb_check_encoding($content, 'UTF-8')) {
            $content = (string) @mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = collect(preg_split("/\\r\\n|\\n|\\r/", $content) ?: [])
            ->map(fn ($l) => trim((string) $l))
            ->filter(fn ($l) => $l !== '' && ! preg_match('/^[;,]+$/', $l))
            ->values();

        if ($lines->count() < 2) {
            return back()->with('error', 'El archivo no tiene datos para importar.');
        }

        $first = (string) $lines[0];
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';

        $rawHeader = str_getcsv($first, $delimiter);
        $header = array_map([$this, 'cleanHeaderKey'], $rawHeader);

        $calibreCols = [];
        foreach ($header as $h) {
            if ($matrix === 'cherries') {
                if (in_array($h, self::CHERRIES_CALIBRES, true)) {
                    $calibreCols[] = $h;
                }
            } else {
                if (preg_match('/^\\d{2,3}$/', $h)) {
                    $calibreCols[] = $h;
                }
            }
        }

        if (empty($calibreCols)) {
            return back()->with('error', 'No se detectaron columnas de calibres. Usa el formato de matriz (columnas 28..120 o L/LD/XL...).');
        }

        $rows = [];
        foreach ($lines->slice(1)->values() as $i => $line) {
            $data = str_getcsv((string) $line, $delimiter);
            $assoc = [];
            foreach ($header as $idx => $h) {
                $assoc[$h] = $data[$idx] ?? null;
            }

            $allowed = [];
            foreach ($calibreCols as $c) {
                $val = trim((string) ($assoc[$c] ?? ''));
                if ($this->isTruthyX($val)) {
                    $allowed[] = $matrix === 'cherries' ? (string) $c : (string) (int) $c;
                }
            }

            $color = (string) ($assoc['COLOR'] ?? $assoc['Color'] ?? '');
            $requireSdp = Str::contains(Str::upper(Str::ascii($color)), 'SDP');

            $sobre = trim((string) ($assoc['SOBRECALIBRE'] ?? $assoc['Sobre Calibre'] ?? ''));
            $mix = trim((string) ($assoc['MIX'] ?? ''));
            if ($sobre === '' && $mix !== '' && mb_strtoupper($mix) !== 'X') {
                $sobre = $mix;
            }

            $peso = trim((string) ($assoc['PESOCJA'] ?? $assoc['Peso Cja'] ?? ''));
            $peso = str_replace(',', '.', $peso);
            $pesoVal = is_numeric($peso) ? (float) $peso : null;

            $cItem = trim((string) ($assoc['EMBALAJE'] ?? $assoc['Embalaje'] ?? ''));
            if ($cItem === '') {
                continue;
            }

            $especie = trim((string) ($assoc['ESPECIE'] ?? $assoc['especie'] ?? ''));
            if ($especie === '') {
                continue;
            }

            $rows[] = [
                'matrix' => $matrix,
                'especie' => $especie,
                'destino' => trim((string) ($assoc['DESTINO'] ?? $assoc['Destino'] ?? '')) ?: null,
                'nota' => trim((string) ($assoc['NOTA'] ?? $assoc['Nota'] ?? '')) ?: null,
                'variedad' => trim((string) ($assoc['VARIEDAD'] ?? $assoc['Variedad'] ?? '')) ?: null,
                'color' => trim((string) ($assoc['COLOR'] ?? $assoc['Color'] ?? '')) ?: null,
                'require_sdp' => $requireSdp,
                'c_item' => $cItem,
                'desc_embalaje' => trim((string) ($assoc['DESCEMBALAJE'] ?? $assoc['Desc Embalaje'] ?? '')) ?: null,
                'peso_caja' => $pesoVal,
                'allowed_calibres' => $allowed,
                'calibres_note' => trim((string) ($assoc['CALIBRES'] ?? $assoc['Calibres'] ?? '')) ?: null,
                'sobre_calibre_note' => $sobre ?: null,
                'priority' => ($i + 1) * 10,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        PackagingMatrixRule::query()->where('matrix', $matrix)->delete();
        if (! empty($rows)) {
            // `insert()` no aplica casts del modelo; el JSON debe ir como string.
            $rows = array_map(function (array $row) {
                $row['allowed_calibres'] = json_encode($row['allowed_calibres'] ?? [], JSON_UNESCAPED_UNICODE);
                $row['created_at'] = (string) ($row['created_at'] ?? now());
                $row['updated_at'] = (string) ($row['updated_at'] ?? now());
                return $row;
            }, $rows);
            PackagingMatrixRule::insert($rows);
        }

        $codes = collect($rows)->pluck('c_item')->filter()->unique()->values()->all();
        $catalog = $this->packagingRepository->getPackagingsByCodes($codes);
        $missing = collect($codes)->filter(fn ($c) => ! array_key_exists($c, $catalog))->values()->all();

        $res = back()->with('success', 'Archivo importado. La matriz quedó actualizada ('.count($rows).' reglas).');
        if (! empty($missing)) {
            $preview = array_slice($missing, 0, 12);
            $more = count($missing) > 12 ? ' (+'.(count($missing) - 12).' más)' : '';
            $res = $res->with('warning', 'Ojo: hay códigos de embalaje no encontrados en catálogo SQLSRV: '.implode(', ', $preview).$more.'.');
        }

        return $res;
    }

    public function exportCsv(Request $request)
    {
        $this->authorizePlanning($request);

        $matrix = (string) ($request->query('matrix') ?: 'carozos');
        if (! in_array($matrix, ['carozos', 'cherries'], true)) {
            $matrix = 'carozos';
        }

        $calibres = $matrix === 'cherries' ? self::CHERRIES_CALIBRES : self::CAROZOS_CALIBRES;

        $header = array_merge(
            ['especie', 'Destino', 'Nota', 'Embalaje', 'Desc Embalaje', 'Peso Cja', 'Variedad', 'Color', 'Calibres'],
            array_map('strval', $calibres),
            ['MIX', 'Sobre Calibre']
        );

        $rules = PackagingMatrixRule::query()
            ->where('matrix', $matrix)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $lines = [];
        $lines[] = implode(';', $header);
        foreach ($rules as $r) {
            $allowed = collect($r->allowed_calibres ?? [])
                ->map(fn ($v) => trim((string) $v))
                ->filter(fn ($v) => $v !== '')
                ->values()
                ->all();
            $row = [
                $r->especie,
                $r->destino ?? '',
                $r->nota ?? '',
                $r->c_item,
                $r->desc_embalaje ?? '',
                $r->peso_caja !== null ? (string) $r->peso_caja : '',
                $r->variedad ?? '',
                $r->color ?? ($r->require_sdp ? 'con SDP' : ''),
                $r->calibres_note ?? '',
            ];
            foreach ($calibres as $c) {
                $row[] = in_array((string) $c, $allowed, true) ? 'X' : '';
            }
            $row[] = '';
            $row[] = $r->sobre_calibre_note ?? '';

            $lines[] = implode(';', array_map(function ($v) {
                $v = (string) $v;
                $v = str_replace(["\r", "\n"], ' ', $v);
                return $v;
            }, $row));
        }

        $content = implode("\n", $lines);
        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="packaging-matrix-'.$matrix.'.csv"',
        ]);
    }

    private function cleanHeaderKey(string $key): string
    {
        $key = preg_replace('/^\\xEF\\xBB\\xBF/', '', $key) ?? $key; // strip BOM
        $key = trim($key);
        $key = str_replace("\u{00A0}", ' ', $key);
        $key = preg_replace('/\\s+/', ' ', $key) ?? $key;

        $upper = mb_strtoupper($key);
        $upper = Str::upper(Str::ascii($upper));
        $upper = str_replace([' ', "\t"], '', $upper);
        $upper = str_replace(['_', '-', '.'], '', $upper);

        // Mantener los nombres originales para columnas de calibre numéricas o etiquetas.
        if (preg_match('/^\\d{2,3}$/', $key)) {
            return (string) (int) $key;
        }
        if (in_array(mb_strtoupper(trim($key)), self::CHERRIES_CALIBRES, true)) {
            return mb_strtoupper(trim($key));
        }

        // Para las demás, devolvemos la forma normalizada para permitir alias.
        return $upper;
    }

    private function isTruthyX(string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return false;
        }
        $u = mb_strtoupper(Str::ascii($v));
        if ($u === '0' || $u === 'NO' || $u === 'N' || $u === 'FALSE') {
            return false;
        }
        return in_array($u, ['X', 'SI', 'S', '1', 'TRUE', 'OK'], true);
    }

    private function validatedRule(Request $request): array
    {
        $data = $request->validate([
            'especie' => ['required', 'string', 'max:80'],
            'destino' => ['nullable', 'string', 'max:60'],
            'nota' => ['nullable', 'string', 'max:60'],
            'variedad' => ['nullable', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:120'],
            'require_sdp' => ['boolean'],
            'c_item' => ['required', 'string', 'max:60'],
            'desc_embalaje' => ['nullable', 'string', 'max:220'],
            'peso_caja' => ['nullable', 'numeric', 'min:0'],
            'allowed_calibres' => ['nullable', 'array'],
            'allowed_calibres.*' => ['string', 'max:10'],
            'calibres_note' => ['nullable', 'string'],
            'sobre_calibre_note' => ['nullable', 'string', 'max:220'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'activo' => ['boolean'],
        ]);

        $data['destino'] = isset($data['destino']) ? trim((string) $data['destino']) : null;
        $data['nota'] = isset($data['nota']) ? trim((string) $data['nota']) : null;
        $data['variedad'] = isset($data['variedad']) ? trim((string) $data['variedad']) : null;
        $data['color'] = isset($data['color']) ? trim((string) $data['color']) : null;
        $data['c_item'] = trim((string) $data['c_item']);

        $data['allowed_calibres'] = collect($data['allowed_calibres'] ?? [])
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        return [
            ...$data,
            'require_sdp' => (bool) ($data['require_sdp'] ?? false),
            'activo' => (bool) ($data['activo'] ?? true),
        ];
    }
}
