<?php

namespace App\Http\Controllers;

use App\Models\ProducerCsg;
use App\Models\User;
use App\Services\SqlsrvProducerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class SagSdpAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureSagRole($request);

        $search = $request->input('search');
        $perPage = (int) $request->input('perPage', 10);

        $query = User::select('rut')
            ->whereNotNull('rut')
            ->whereNotNull('csg')
            ->where('is_active', true)
            ->distinct();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('rut', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        $paginatedRuts = $query->paginate($perPage);
        $rutsOnPage = $paginatedRuts->pluck('rut')->all();

        $records = User::query()
            ->whereIn('rut', $rutsOnPage)
            ->whereNotNull('csg')
            ->where('is_active', true)
            ->get()
            ->groupBy('rut');

        $producers = $records->map(function ($items, $rut) {
            $first = $items->first();

            return [
                'rut' => $rut,
                'name' => $first?->name,
                'email' => $first?->email,
                'csg_records' => $items->map(fn ($user) => [
                    'id' => $user->id,
                    'csg' => $user->csg,
                    'idprod' => $user->idprod,
                    'predio' => $user->predio,
                    'comuna' => $user->comuna,
                    'provincia' => $user->provincia,
                    'direccion' => $user->direccion,
                ])->values(),
            ];
        })->values();

        $paginatedProducers = new LengthAwarePaginator(
            $producers,
            $paginatedRuts->total(),
            $paginatedRuts->perPage(),
            $paginatedRuts->currentPage(),
            ['path' => $paginatedRuts->path()]
        );

        return Inertia::render('Sag/SdpAssignments/Index', [
            'producers' => $paginatedProducers,
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function show(Request $request, string $rut): Response
    {
        $this->ensureSagRole($request);

        return Redirect::route('sag.sdp-assignments.index', ['search' => $rut]);
    }

    public function data(Request $request, string $rut): JsonResponse
    {
        $this->ensureSagRole($request);

        $normalized = $this->normalizeRut($rut);
        $users = User::query()
            ->whereRaw($this->normalizeRutExpression().' = ?', [$normalized])
            ->whereNotNull('csg')
            ->where('is_active', true)
            ->get();

        if ($users->isEmpty()) {
            return response()->json([
                'message' => 'Productor no encontrado.',
                'producer' => null,
                'assignments' => [],
            ], 404);
        }

        $producer = [
            'rut' => $rut,
            'name' => $users->first()->name,
            'csgs' => $users->map(fn ($user) => [
                'id' => $user->id,
                'csg' => $user->csg,
                'idprod' => $user->idprod,
                'predio' => $user->predio,
                'direccion' => $user->direccion,
            ])->values(),
        ];

        $assignments = ProducerCsg::query()
            ->whereIn('user_id', $users->pluck('id')->all())
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'csg_code' => $row->csg_code,
                'sdp' => $row->sdp,
                'variedad' => $row->variedad,
                'especie' => $row->especie,
                'clasificacion' => $row->clasificacion,
                'sdp_validado' => (bool) $row->sdp_validado,
                'sdp_validado_at' => $row->sdp_validado_at ? (string) $row->sdp_validado_at : null,
            ])->values();

        return response()->json([
            'producer' => $producer,
            'assignments' => $assignments,
        ]);
    }

    public function fetchSdp(Request $request): JsonResponse
    {
        $this->ensureSagRole($request);

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

    public function store(Request $request, string $rut, SqlsrvProducerService $service): JsonResponse
    {
        $this->ensureSagRole($request);

        $validated = $request->validate([
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.csg_code' => ['required', 'string', 'max:60'],
            'assignments.*.sdp' => ['required', 'string', 'max:60'],
            'assignments.*.variedad' => ['required', 'string', 'max:255'],
            'assignments.*.especie' => ['nullable', 'string', 'max:255'],
            'assignments.*.clasificacion' => ['nullable', 'string', 'max:10'],
        ]);

        $normalized = $this->normalizeRut($rut);
        $users = User::query()
            ->whereRaw($this->normalizeRutExpression().' = ?', [$normalized])
            ->whereNotNull('csg')
            ->where('is_active', true)
            ->get();

        if ($users->isEmpty()) {
            return response()->json([
                'created' => 0,
                'updated' => 0,
                'skipped' => count($validated['assignments']),
                'message' => 'No hay productores activos con CSG para este RUT.',
            ], 404);
        }

        $usersByCsg = $users->keyBy(fn ($user) => (string) $user->csg);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $sqlsrvUpdated = 0;
        $sqlsrvErrors = [];

        foreach ($validated['assignments'] as $assignment) {
            $csg = trim((string) $assignment['csg_code']);
            $user = $usersByCsg->get($csg);
            if (! $user) {
                $skipped++;
                continue;
            }

            $row = ProducerCsg::updateOrCreate([
                'user_id' => $user->id,
                'csg_code' => $csg,
                'variedad' => trim((string) $assignment['variedad']),
            ], [
                'idprod' => $user->idprod,
                'sdp' => $assignment['sdp'],
                'especie' => $assignment['especie'] ?? null,
                'clasificacion' => $assignment['clasificacion'] ?? null,
                'sdp_validado' => true,
                'sdp_validado_at' => now(),
            ]);

            if ($row->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            $sqlsrvResult = $service->updateCentroCostoSdp(
                $csg,
                trim((string) $assignment['variedad']),
                trim((string) $assignment['sdp'])
            );
            if ($sqlsrvResult['updated'] > 0) {
                $sqlsrvUpdated += $sqlsrvResult['updated'];
            } elseif ($sqlsrvResult['error']) {
                $sqlsrvErrors[] = [
                    'csg' => $csg,
                    'variedad' => $assignment['variedad'],
                    'error' => $sqlsrvResult['error'],
                ];
            } else {
                $sqlsrvErrors[] = [
                    'csg' => $csg,
                    'variedad' => $assignment['variedad'],
                    'error' => 'Centro de costo no encontrado para CSG/variedad.',
                ];
            }
        }

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'sqlsrv_updated' => $sqlsrvUpdated,
            'sqlsrv_errors' => $sqlsrvErrors,
        ]);
    }

    private function ensureSagRole(Request $request): void
    {
        $user = $request->user();
        $isAllowed = $user && method_exists($user, 'hasRole')
            && $user->hasRole(['Admin', 'Administrador', 'Sag', 'Agronomo']);

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
}
