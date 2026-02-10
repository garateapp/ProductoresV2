<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ProspectoProductor;
use App\Models\ProducerCsg;
use App\Models\Variedad;
use App\Services\SqlsrvProducerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class ContractProducerFlowController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureContractRole($request);

        $producers = User::whereNotNull('idprod')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Contracts/ProducerFlow', [
            'producers' => $producers,
        ]);
    }

    public function checkRut(Request $request): JsonResponse
    {
        $this->ensureContractRole($request);

        $validated = $request->validate([
            'rut' => ['required', 'string', 'max:60'],
        ]);

        $normalized = $this->normalizeRut($validated['rut']);

        $user = User::query()
            ->whereRaw($this->normalizeRutExpression().' = ?', [$normalized])
            ->first();

        $prospecto = ProspectoProductor::query()
            ->whereRaw($this->normalizeRutExpression('rut').' = ?', [$normalized])
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'exists' => (bool) $user,
            'normalized_rut' => $normalized,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rut' => $user->rut,
                'is_active' => (bool) $user->is_active,
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames() : [],
            ] : null,
            'prospecto' => $prospecto ? [
                'id' => $prospecto->id,
                'razon_social' => $prospecto->razon_social,
                'rut' => $prospecto->rut,
                'email' => $prospecto->email,
                'comuna_comercial' => $prospecto->comuna_comercial,
                'comuna_predio' => $prospecto->comuna_predio,
                'validated_at' => optional($prospecto->validated_at)->toDateTimeString(),
                'producer_id' => $prospecto->producer_id,
            ] : null,
        ]);
    }

    public function activate(Request $request): JsonResponse
    {
        $this->ensureContractRole($request);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $user->is_active = true;
        $user->save();

        if (method_exists($user, 'assignRole') && ! $user->hasRole('Productor')) {
            $user->assignRole('Productor');
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'is_active' => (bool) $user->is_active,
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames() : [],
            ],
        ]);
    }

    public function fetchSag(Request $request): JsonResponse
    {
        $this->ensureContractRole($request);

        $validated = $request->validate([
            'rut' => ['required', 'string', 'max:60'],
        ]);

        $response = Http::timeout(15)->get('https://sra.sag.gob.cl/SRA_COMUNES/AJAX/cargarComunes.asp', [
            'mueDetCodSag' => 'S',
            'rut' => $validated['rut'],
            'tipPart' => 'PROD',
        ]);

        if (! $response->ok()) {
            return response()->json([
                'items' => [],
                'error' => 'No fue posible consultar SAG.',
            ], 502);
        }

        $items = $this->parseSagHtml($response->body());

        return response()->json([
            'items' => $items,
        ]);
    }

    public function fetchSdp(Request $request): JsonResponse
    {
        $this->ensureContractRole($request);

        $validated = $request->validate([
            'rut' => ['required', 'string', 'max:60'],
        ]);

        $response = Http::asForm()->timeout(20)->post('https://sispmex.sag.gob.cl/pubsber/reporteSDP.asp', [
            'BRIDREGION' => 0,
            'BRMUESTREO' => 99,
            'BRIDCOMUNA' => 0,
            'BRESTADO' => 99,
            'BCODSAG' => '',
            'RUT' => $validated['rut'],
            'BRCODSAG' => 'Buscar',
        ]);

        if (! $response->ok()) {
            return response()->json([
                'items' => [],
                'error' => 'No fue posible consultar SDP.',
            ], 502);
        }

        $items = $this->parseSdpHtml($response->body());

        return response()->json([
            'items' => $items,
        ]);
    }

    public function syncSdp(Request $request): JsonResponse
    {
        $this->ensureContractRole($request);

        $validated = $request->validate([
            'rut' => ['required', 'string', 'max:60'],
            'sag_items' => ['nullable', 'array'],
            'sag_items.*.csg_code' => ['nullable', 'string', 'max:60'],
            'sag_items.*.predio' => ['nullable', 'string'],
            'sag_items.*.direccion' => ['nullable', 'string'],
            'sag_items.*.variedades' => ['nullable', 'array'],
            'sag_items.*.especie_variedades' => ['nullable', 'array'],
            'sag_items.*.especie_variedades.*.raw' => ['nullable', 'string'],
            'sag_items.*.especie_variedades.*.especie' => ['nullable', 'string'],
            'sag_items.*.especie_variedades.*.variedad' => ['nullable', 'string'],
            'sdp_items' => ['required', 'array', 'min:1'],
            'sdp_items.*.sdp_code' => ['nullable', 'string', 'max:60'],
            'sdp_items.*.sdp_name' => ['nullable', 'string'],
            'sdp_items.*.variedades' => ['nullable', 'array'],
        ]);

        $normalized = $this->normalizeRut($validated['rut']);
        $users = User::query()
            ->whereRaw($this->normalizeRutExpression().' = ?', [$normalized])
            ->get();

        $sdpVarietyMap = $this->buildSdpVarietyMap($validated['sdp_items']);
        if (empty($sdpVarietyMap)) {
            return response()->json([
                'updated' => 0,
                'skipped' => 0,
                'message' => 'No se encontraron variedades SDP para actualizar.',
            ]);
        }

        if ($users->isEmpty()) {
            return response()->json([
                'updated' => 0,
                'created' => 0,
                'skipped' => 0,
                'message' => 'No hay productores en el portal para este RUT.',
            ]);
        }

        $updated = 0;
        $created = 0;
        $skipped = 0;

        $sagItems = $validated['sag_items'] ?? [];
        if (! empty($sagItems)) {
            $usersByCsg = $users->keyBy(fn ($user) => (string) ($user->csg ?? ''));
            foreach ($sagItems as $item) {
                $csg = trim((string) ($item['csg_code'] ?? ''));
                if ($csg === '') {
                    continue;
                }
                $user = $usersByCsg->get($csg);
                if (! $user) {
                    $skipped++;
                    continue;
                }

                $especieByVariedad = [];
                $variedades = collect($item['especie_variedades'] ?? [])
                    ->map(function ($entry) use (&$especieByVariedad) {
                        $variedad = trim((string) ($entry['variedad'] ?? ''));
                        $especie = trim((string) ($entry['especie'] ?? ''));
                        if ($variedad === '' && ! empty($entry['raw'])) {
                            $parts = preg_split('/\s*[\-–—]\s*/u', (string) $entry['raw'], 2);
                            $especie = $especie ?: (isset($parts[0]) ? trim($parts[0]) : '');
                            $variedad = isset($parts[1]) ? trim($parts[1]) : '';
                        }
                        if ($variedad !== '' && $especie !== '') {
                            $especieByVariedad[mb_strtolower($variedad)] = $especie;
                        }
                        return $variedad;
                    })
                    ->filter()
                    ->unique()
                    ->values();

                if ($variedades->isEmpty()) {
                    $variedades = collect($item['variedades'] ?? [])
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->unique()
                        ->values();
                }

                foreach ($variedades as $variedad) {
                    $key = mb_strtolower($variedad);
                    $sdpInfo = $sdpVarietyMap[$key] ?? null;
                    if (! $sdpInfo) {
                        $skipped++;
                        continue;
                    }

                    $especie = $especieByVariedad[$key] ?? ($sdpInfo['especie'] ?? null);

                    $row = ProducerCsg::updateOrCreate([
                        'user_id' => $user->id,
                        'csg_code' => $csg,
                        'variedad' => $variedad,
                    ], [
                        'idprod' => $user->idprod,
                        'predio_name' => $item['predio'] ?? '',
                        'predio_direccion' => $item['direccion'] ?? '',
                        'sdp' => $sdpInfo['sdp'] ?? null,
                        'especie' => $especie,
                        'clasificacion' => $sdpInfo['clasificacion'] ?? null,
                        'sdp_validado' => false,
                        'sdp_validado_at' => null,
                    ]);

                    if ($row->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                }
            }
        } else {
            $rows = ProducerCsg::query()
                ->whereIn('user_id', $users->pluck('id')->all())
                ->get();

            foreach ($rows as $row) {
                $key = mb_strtolower((string) $row->variedad);
                $sdpInfo = $sdpVarietyMap[$key] ?? null;
                if (! $sdpInfo) {
                    $skipped++;
                    continue;
                }

                $changes = [];
                if (($row->sdp ?? null) !== ($sdpInfo['sdp'] ?? null)) {
                    $changes['sdp'] = $sdpInfo['sdp'] ?? null;
                }
                if (($row->clasificacion ?? null) !== ($sdpInfo['clasificacion'] ?? null)) {
                    $changes['clasificacion'] = $sdpInfo['clasificacion'] ?? null;
                }

                if ($changes) {
                    $row->fill($changes);
                    $row->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        }

        return response()->json([
            'updated' => $updated,
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    public function storeSag(Request $request): JsonResponse
    {
        $this->ensureContractRole($request);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'csg_code' => ['nullable', 'string', 'max:60'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'variedades' => ['nullable', 'array'],
            'variedades.*' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        if (! empty($validated['csg_code'])) {
            $user->csg = $validated['csg_code'];
        }

        if (! empty($validated['direccion'])) {
            $user->direccion = $validated['direccion'];
        }

        $user->save();

        $requested = collect($validated['variedades'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($requested->isEmpty()) {
            return response()->json([
                'success' => true,
                'attached_variedades' => [],
                'missing_variedades' => [],
            ]);
        }

        $lowerNames = $requested->map(fn ($value) => mb_strtolower($value))->values();

        $variedades = Variedad::query()
            ->whereIn(DB::raw('LOWER(name)'), $lowerNames->all())
            ->get(['id', 'name']);

        $foundLower = $variedades
            ->map(fn ($variedad) => mb_strtolower((string) $variedad->name))
            ->unique();

        $missing = $lowerNames
            ->diff($foundLower)
            ->values()
            ->all();

        $attached = [];
        foreach ($variedades as $variedad) {
            DB::table('user_variedad')->updateOrInsert([
                'user_id' => $user->id,
                'variedad_id' => $variedad->id,
            ]);
            $attached[] = [
                'id' => $variedad->id,
                'name' => $variedad->name,
            ];
        }

        return response()->json([
            'success' => true,
            'attached_variedades' => $attached,
            'missing_variedades' => $missing,
        ]);
    }

    public function sqlsrvCheck(Request $request, SqlsrvProducerService $service): JsonResponse
    {
        $this->ensureContractRole($request);

        $validated = $request->validate([
            'rut' => ['required', 'string', 'max:60'],
            'sag_items' => ['nullable', 'array'],
            'sag_items.*.csg_code' => ['nullable', 'string', 'max:60'],
            'sag_items.*.direccion' => ['nullable', 'string'],
        ]);

        $result = $service->checkByRut($validated['rut'], $validated['sag_items'] ?? []);

        return response()->json($result);
    }

    public function sqlsrvCreate(Request $request, SqlsrvProducerService $service): JsonResponse
    {
        $this->ensureContractRole($request);

        $validated = $request->validate([
            'rut' => ['required', 'string', 'max:60'],
            'razon_social' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'comuna' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'in:create,update'],
            'sag_items' => ['required', 'array', 'min:1'],
            'sag_items.*.csg_code' => ['required', 'string', 'max:60'],
            'sag_items.*.predio' => ['nullable', 'string'],
            'sag_items.*.direccion' => ['nullable', 'string'],
            'sag_items.*.status' => ['nullable', 'string', 'max:60'],
            'sag_items.*.especie_variedades' => ['nullable', 'array'],
            'sag_items.*.especie_variedades.*.raw' => ['nullable', 'string'],
            'sag_items.*.especie_variedades.*.especie' => ['nullable', 'string'],
            'sag_items.*.especie_variedades.*.variedad' => ['nullable', 'string'],
            'sdp_items' => ['nullable', 'array'],
            'sdp_items.*.sdp_code' => ['nullable', 'string', 'max:60'],
            'sdp_items.*.sdp_name' => ['nullable', 'string'],
            'sdp_items.*.variedades' => ['nullable', 'array'],
        ]);

        $result = $service->createFromSag($validated);
        $portal = $this->syncPortalProducers($validated, $result);

        return response()->json([
            'sqlsrv' => $result,
            'portal' => $portal,
        ]);
    }

    private function ensureContractRole(Request $request): void
    {
        $user = $request->user();
        $isAllowed = $user && method_exists($user, 'hasRole') && $user->hasRole(['Admin', 'Administrador', 'Contrato']);

        abort_unless($isAllowed, 403);
    }

    private function normalizeRut(string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', $rut));
    }

    private function normalizeRutExpression(string $column = 'rut'): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE({$column}, '.', ''), '-', ''), ' ', ''))";
    }

    private function parseSagHtml(string $html): array
    {
        $items = [];
        if (trim($html) === '') {
            return $items;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length === 0) {
            return $items;
        }

        $table = $tables->item(0);
        $rows = $table->getElementsByTagName('tr');

        foreach ($rows as $row) {
            $headers = $row->getElementsByTagName('th');
            if ($headers->length > 0) {
                continue;
            }

            $cells = $row->getElementsByTagName('td');
            if ($cells->length < 3) {
                continue;
            }

            $csgText = trim(preg_replace('/\s+/', ' ', $cells->item(0)->textContent));
            if (stripos($csgText, 'Cód. SAG') !== false) {
                continue;
            }
            preg_match('/([0-9Kk]+)/', $csgText, $csgMatch);
            $csgCode = $csgMatch[1] ?? null;
            $status = str_contains(strtoupper($csgText), 'ACTIVO') ? 'ACTIVO' : null;

            $predioText = trim(preg_replace('/\s+/', ' ', $cells->item(1)->textContent));
            $predio = null;
            $direccion = null;
            if ($predioText !== '') {
                if (stripos($predioText, 'Dirección') !== false) {
                    [$predioPart, $direccionPart] = preg_split('/Dirección/i', $predioText, 2);
                    $predio = trim($predioPart);
                    $direccion = trim($direccionPart);
                } else {
                    $predio = $predioText;
                }
            }

            $variedades = [];
            $especieVariedades = [];
            $liNodes = $cells->item(2)->getElementsByTagName('li');
            if ($liNodes->length > 0) {
                foreach ($liNodes as $li) {
                    $text = trim(preg_replace('/\s+/', ' ', $li->textContent));
                    if ($text === '') {
                        continue;
                    }
                    $parts = preg_split('/\s*[\-–—]\s*/u', $text, 2);
                    $especie = isset($parts[0]) ? trim($parts[0]) : null;
                    $variedad = isset($parts[1]) ? trim($parts[1]) : null;
                    if ($variedad) {
                        $variedades[] = $variedad;
                    }
                    $especieVariedades[] = [
                        'raw' => $text,
                        'especie' => $especie ?: null,
                        'variedad' => $variedad ?: null,
                    ];
                }
            } else {
                $rawText = trim(preg_replace('/\s+/', ' ', $cells->item(2)->textContent));
                if ($rawText !== '') {
                    foreach (preg_split('/\r\n|\r|\n/', $rawText) as $line) {
                        $text = trim($line);
                        if ($text === '') {
                            continue;
                        }
                        $parts = preg_split('/\s*[\-–—]\s*/u', $text, 2);
                        $especie = isset($parts[0]) ? trim($parts[0]) : null;
                        $variedad = isset($parts[1]) ? trim($parts[1]) : null;
                        if ($variedad) {
                            $variedades[] = $variedad;
                        }
                        $especieVariedades[] = [
                            'raw' => $text,
                            'especie' => $especie ?: null,
                            'variedad' => $variedad ?: null,
                        ];
                    }
                }
            }

            $items[] = [
                'csg_code' => $csgCode,
                'status' => $status,
                'predio' => $predio,
                'direccion' => $direccion,
                'variedades' => array_values(array_unique($variedades)),
                'especie_variedades' => $especieVariedades,
            ];
        }

        return $items;
    }

    private function parseSdpHtml(string $html): array
    {
        $items = [];
        if (trim($html) === '') {
            return $items;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length === 0) {
            return $items;
        }

        $target = null;
        foreach ($tables as $table) {
            $text = $table->textContent;
            if (stripos($text, 'Código SAG') !== false && stripos($text, 'Sitio de Producción') !== false) {
                $target = $table;
                break;
            }
        }
        if (! $target) {
            return $items;
        }

        $rows = $target->getElementsByTagName('tr');
        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length < 6) {
                continue;
            }

            $codeCell = trim(preg_replace('/\s+/', ' ', $cells->item(0)->textContent));
            if ($codeCell === '' || stripos($codeCell, 'Código SAG') !== false) {
                continue;
            }
            preg_match('/([0-9]{4,})/', $codeCell, $codeMatch);
            $sdpCode = $codeMatch[1] ?? null;
            if (! $sdpCode) {
                continue;
            }
            $sdpName = null;
            if (stripos($codeCell, 'SDP:') !== false) {
                $parts = explode('SDP:', $codeCell, 2);
                $sdpName = trim($parts[1] ?? '');
            }

            $muestreo = trim(preg_replace('/\s+/', ' ', $cells->item(1)->textContent));

            $variedadesRaw = $cells->item(2)->textContent ?? '';
            $normalizedVariedades = trim(preg_replace('/\s+/', ' ', (string) $variedadesRaw));
            $pattern = '/([A-ZÁÉÍÓÚÜÑ]+\.[A-Z0-9\s\-\']+?\s*-\s*Clasificac(?:i[oó]n|ion|in)\s*:\s*[ABC](?:\*)?)/iu';
            if ($normalizedVariedades !== '' && preg_match_all($pattern, $normalizedVariedades, $matches) && ! empty($matches[1])) {
                $variedadesLines = array_values(array_filter(array_map('trim', $matches[1])));
            } else {
                $variedadesLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $variedadesRaw))));
            }

            $comuna = trim(preg_replace('/\s+/', ' ', $cells->item(3)->textContent));
            $region = trim(preg_replace('/\s+/', ' ', $cells->item(4)->textContent));
            $fecha = trim(preg_replace('/\s+/', ' ', $cells->item(5)->textContent));

            $items[] = [
                'sdp_code' => $sdpCode,
                'sdp_name' => $sdpName,
                'muestreo' => $muestreo,
                'variedades' => $variedadesLines,
                'comuna' => $comuna,
                'region' => $region,
                'fecha_registro' => $fecha,
            ];
        }

        return $items;
    }

    private function syncPortalProducers(array $payload, array $sqlsrvResult): array
    {
        $normalizedRut = $this->normalizeRut($payload['rut'] ?? '');
        $razonSocial = $payload['razon_social'] ?? '';
        $email = $payload['email'] ?? null;
        $username = 'gre-'.$normalizedRut;
        $sdpVarietyMap = $this->buildSdpVarietyMap($payload['sdp_items'] ?? []);

        $results = [];
        $errors = [];
        $byCsg = collect($sqlsrvResult['results'] ?? [])->keyBy('csg');

        $activeSagItems = array_values(array_filter($payload['sag_items'] ?? [], function ($item) {
            $status = strtoupper((string) ($item['status'] ?? ''));
            return $status === 'ACTIVO';
        }));

        foreach ($activeSagItems as $item) {
            $csg = trim((string) ($item['csg_code'] ?? ''));
            if ($csg === '') {
                continue;
            }

            $idprod = (string) (($byCsg[$csg]['entity_id'] ?? '') ?: '');
            $direccion = $item['direccion'] ?? '';
            $predio = $item['predio'] ?? '';

            try {
                $user = User::query()
                    ->where('rut', $normalizedRut)
                    ->where('csg', $csg)
                    ->first();

                if (! $user) {
                    $user = User::create([
                        'name' => $razonSocial,
                        'email' => $email,
                        'password' => Hash::make('gre1234'),
                        'rut' => $normalizedRut,
                        'user' => $username,
                        'direccion' => $direccion,
                        'emnotification' => true,
                        'enviomasivo' => true,
                        'csg' => $csg,
                        'idprod' => $idprod,
                        'is_active' => true,
                    ]);

                    if (method_exists($user, 'assignRole') && ! $user->hasRole('Productor')) {
                        $user->assignRole('Productor');
                    }

                    $status = 'created';
                } else {
                    $user->fill([
                        'name' => $razonSocial,
                        'email' => $email ?: $user->email,
                        'user' => $username,
                        'direccion' => $direccion ?: $user->direccion,
                        'emnotification' => true,
                        'enviomasivo' => true,
                        'csg' => $csg,
                        'idprod' => $idprod !== '' ? $idprod : $user->idprod,
                        'is_active' => true,
                    ]);
                    $user->save();
                    $status = 'updated';
                }

                $especieByVariedad = [];
                $variedades = collect($item['especie_variedades'] ?? [])
                    ->map(function ($entry) use (&$especieByVariedad) {
                        $variedad = trim((string) ($entry['variedad'] ?? ''));
                        $especie = trim((string) ($entry['especie'] ?? ''));
                        if ($variedad === '' && ! empty($entry['raw'])) {
                            $parts = preg_split('/\s*[\-–—]\s*/u', (string) $entry['raw'], 2);
                            $especie = $especie ?: (isset($parts[0]) ? trim($parts[0]) : '');
                            $variedad = isset($parts[1]) ? trim($parts[1]) : '';
                        }
                        if ($variedad !== '' && $especie !== '') {
                            $especieByVariedad[mb_strtolower($variedad)] = $especie;
                        }
                        return $variedad;
                    })
                    ->filter()
                    ->unique()
                    ->values();

                if ($variedades->isEmpty()) {
                    $variedades = collect($item['variedades'] ?? [])
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->unique()
                        ->values();
                }

                foreach ($variedades as $variedad) {
                    $key = mb_strtolower($variedad);
                    $sdpInfo = $sdpVarietyMap[$key] ?? null;
                    $especie = $especieByVariedad[$key] ?? ($sdpInfo['especie'] ?? null);
                    $clasificacion = $sdpInfo['clasificacion'] ?? null;
                    ProducerCsg::updateOrCreate([
                        'user_id' => $user->id,
                        'csg_code' => $csg,
                        'variedad' => $variedad,
                    ], [
                        'idprod' => $idprod,
                        'predio_name' => $predio,
                        'predio_direccion' => $direccion,
                        'sdp' => $sdpInfo['sdp'] ?? null,
                        'especie' => $especie,
                        'clasificacion' => $clasificacion,
                        'sdp_validado' => false,
                        'sdp_validado_at' => null,
                    ]);
                }

                $results[] = [
                    'csg' => $csg,
                    'user_id' => $user->id,
                    'status' => $status,
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'csg' => $csg,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'results' => $results,
            'errors' => $errors,
        ];
    }

    private function buildSdpVarietyMap(array $sdpItems): array
    {
        $map = [];
        foreach ($sdpItems as $item) {
            $sdpCode = $item['sdp_code'] ?? null;
            if (! $sdpCode) {
                continue;
            }
            foreach (($item['variedades'] ?? []) as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }

                $entries = $this->extractSdpEntries($line);
                foreach ($entries as $entry) {
                    $entry = trim($entry);
                    if ($entry === '') {
                        continue;
                    }
                    $classification = null;
                    if (preg_match('/Clasificac(?:i[oó]n|ion|in)\s*:\s*([ABC](?:\*)?)/i', $entry, $match)) {
                        $classification = strtoupper($match[1]);
                    }
                    $parts = preg_split('/\s*-\s*/', $entry, 2);
                    $left = trim((string) ($parts[0] ?? ''));
                    if ($left === '') {
                        continue;
                    }
                    $dotParts = explode('.', $left, 2);
                    $especie = trim((string) ($dotParts[0] ?? ''));
                    $variedad = trim((string) ($dotParts[1] ?? $dotParts[0]));
                    if ($variedad === '') {
                        continue;
                    }
                    $key = mb_strtolower($variedad);
                    if (! isset($map[$key])) {
                        $map[$key] = [
                            'sdp' => $sdpCode,
                            'especie' => $especie ?: null,
                            'clasificacion' => $classification,
                        ];
                    }
                }
            }
        }
        return $map;
    }

    private function extractSdpEntries(string $line): array
    {
        $pattern = '/([A-ZÁÉÍÓÚÜÑ]+\.[A-Z0-9\s\-\']+?\s*-\s*Clasificac(?:i[oó]n|ion|in)\s*:\s*[ABC](?:\*)?)/iu';
        if (preg_match_all($pattern, $line, $matches)) {
            return array_values(array_filter(array_map('trim', $matches[1])));
        }

        return [$line];
    }
}
