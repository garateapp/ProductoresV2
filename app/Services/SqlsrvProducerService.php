<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SqlsrvProducerService
{
    public function normalizeRut(?string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut));
    }

    public function findEntitiesByRut(string $rut): Collection
    {
        $normalized = $this->normalizeRut($rut);

        return DB::connection('sqlsrv')
            ->table('ADM_P_Entidades')
            ->select([
                'id',
                'rut',
                'nombre',
                'direccion',
                'codigo_sag',
                'tipo_juridico',
                'sucursal',
                'nombre_sucursal',
            ])
            ->where('tipo_juridico', 1)
            ->whereRaw("REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '') = ?", [$normalized])
            ->orderBy('id')
            ->get();
    }

    public function findComunaIdByName(?string $comuna): ?int
    {
        $comuna = trim((string) $comuna);
        if ($comuna === '') {
            return null;
        }

        $row = DB::connection('sqlsrv')
            ->table('ADM_P_Comunas')
            ->select(['id', 'nombre'])
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($comuna)])
            ->first();

        if ($row) {
            return (int) $row->id;
        }

        $fallback = DB::connection('sqlsrv')
            ->table('ADM_P_Comunas')
            ->select(['id', 'nombre'])
            ->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($comuna).'%'])
            ->first();

        return $fallback ? (int) $fallback->id : null;
    }

    public function getNextSucursalNumber(Collection $entities): int
    {
        $max = 0;
        foreach ($entities as $entity) {
            $value = preg_replace('/\D/', '', (string) ($entity->sucursal ?? ''));
            if ($value === '') {
                continue;
            }
            $num = (int) $value;
            if ($num > $max) {
                $max = $num;
            }
        }

        return $max + 1;
    }

    public function analyzeMissingCsg(Collection $entities, array $sagItems): array
    {
        $records = [];
        $suggestedMatches = [];

        $candidates = $entities->filter(fn ($row) => empty($row->codigo_sag));
        foreach ($candidates as $row) {
            $best = ['similarity' => 0, 'csg' => null, 'direccion' => null];
            foreach ($sagItems as $item) {
                $similarity = $this->addressSimilarity($row->direccion ?? '', $item['direccion'] ?? '');
                if ($similarity > $best['similarity']) {
                    $best = [
                        'similarity' => $similarity,
                        'csg' => $item['csg_code'] ?? null,
                        'direccion' => $item['direccion'] ?? null,
                    ];
                }
            }
            $records[] = [
                'id' => $row->id,
                'sucursal' => $row->sucursal,
                'direccion' => $row->direccion,
                'best_match_csg' => $best['csg'],
                'best_match_similarity' => $best['similarity'],
                'best_match_direccion' => $best['direccion'],
            ];
        }

        foreach ($sagItems as $item) {
            $csg = $item['csg_code'] ?? null;
            if (! $csg) {
                continue;
            }
            $bestRow = null;
            $bestScore = 0;
            foreach ($records as $record) {
                $score = $record['best_match_csg'] === $csg ? $record['best_match_similarity'] : 0;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestRow = $record;
                }
            }
            if ($bestRow) {
                $suggestedMatches[$csg] = [
                    'entity_id' => $bestRow['id'],
                    'similarity' => $bestRow['best_match_similarity'],
                    'direccion' => $bestRow['direccion'],
                ];
            }
        }

        $needsConfirmation = collect($records)->contains(function ($record) {
            return ($record['best_match_similarity'] ?? 0) >= 65;
        });

        return [
            'records' => $records,
            'suggested_matches' => $suggestedMatches,
            'needs_confirmation' => $needsConfirmation,
        ];
    }

    public function checkByRut(string $rut, array $sagItems): array
    {
        $entities = $this->findEntitiesByRut($rut);
        $csgs = collect($sagItems)
            ->pluck('csg_code')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $csgExists = [];
        foreach ($csgs as $csg) {
            if ($entities->firstWhere('codigo_sag', $csg)) {
                $csgExists[] = $csg;
            }
        }

        $missingAnalysis = $this->analyzeMissingCsg($entities, $sagItems);

        return [
            'exists' => $entities->isNotEmpty(),
            'csg_exists' => $csgExists,
            'records_without_csg' => $missingAnalysis['records'],
            'suggested_matches' => $missingAnalysis['suggested_matches'],
            'needs_confirmation' => $missingAnalysis['needs_confirmation'],
        ];
    }

    public function createFromSag(array $payload): array
    {
        $rut = $payload['rut'];
        $razonSocial = $payload['razon_social'] ?? '';
        $comuna = $payload['comuna'] ?? null;
        $sagItems = array_values(array_filter($payload['sag_items'] ?? [], function ($item) {
            $status = strtoupper((string) ($item['status'] ?? ''));
            return $status === 'ACTIVO';
        }));
        $action = $payload['action'] ?? 'create';

        $entities = $this->findEntitiesByRut($rut);
        $byCsg = $entities->filter(fn ($row) => ! empty($row->codigo_sag))
            ->keyBy(fn ($row) => (string) $row->codigo_sag);

        $missingAnalysis = $this->analyzeMissingCsg($entities, $sagItems);
        $suggestedMatches = $missingAnalysis['suggested_matches'];

        $comunaId = $this->findComunaIdByName($comuna);
        $nextSucursal = $this->getNextSucursalNumber($entities);

        $results = [];
        $usedUpdates = [];

        foreach ($sagItems as $item) {
            $csg = trim((string) ($item['csg_code'] ?? ''));
            if ($csg === '') {
                continue;
            }

            if ($byCsg->has($csg)) {
                $entity = $byCsg->get($csg);
                $results[] = [
                    'csg' => $csg,
                    'entity_id' => $entity->id,
                    'status' => 'exists',
                ];
                continue;
            }

            $entityId = null;
            if ($action === 'update' && isset($suggestedMatches[$csg])) {
                $candidateId = $suggestedMatches[$csg]['entity_id'] ?? null;
                if ($candidateId && ! in_array($candidateId, $usedUpdates, true)) {
                    $this->updateEntityCsg($candidateId, $csg, $item['predio'] ?? '', $item['direccion'] ?? '', $razonSocial);
                    $entityId = $candidateId;
                    $usedUpdates[] = $candidateId;
                    $results[] = [
                        'csg' => $csg,
                        'entity_id' => $entityId,
                        'status' => 'updated',
                    ];
                }
            }

            if (! $entityId) {
                $sucursal = str_pad((string) $nextSucursal, 3, '0', STR_PAD_LEFT);
                $nextSucursal++;
                $entityId = $this->createEntity([
                    'rut' => $rut,
                    'razon_social' => $razonSocial,
                    'sucursal' => $sucursal,
                    'tipo_sucursal' => 'Fisica',
                    'nombre_sucursal' => $this->buildNombreSucursal($csg, $item['predio'] ?? '', $razonSocial),
                    'direccion' => $item['direccion'] ?? '',
                    'id_adm_comunas' => $comunaId,
                    'codigo' => $csg,
                    'tipo' => 'Productor',
                ]);

                if ($entityId) {
                    $this->updateEntityCsg(
                        $entityId,
                        $csg,
                        $item['predio'] ?? '',
                        $item['direccion'] ?? '',
                        $razonSocial
                    );
                }

                $results[] = [
                    'csg' => $csg,
                    'entity_id' => $entityId,
                    'status' => 'created',
                ];
            }
        }
        Log::debug('Entities processed', ['results' => $results]);
        $centroCostoSummary = $this->createCentrosCosto($results, $razonSocial, $sagItems);

        return [
            'results' => $results,
            'centros_costo' => $centroCostoSummary,
        ];
    }

    private function createEntity(array $data): int
    {
        Log::debug('Creating entity', ['data' => $data]);
        $normalizedRut = $this->normalizeRut($data['rut'] ?? '');

        DB::connection('sqlsrv')->statement(
            "EXEC ADM_P_Entidades_Grabar
                @id = ?,
                @rut = ?,
                @nombre = ?,
                @sucursal = ?,
                @tipo_sucursal = ?,
                @nombre_sucursal = ?,
                @direccion = ?,
                @id_adm_p_comunas = ?,
                @id_matriz = ?,
                @codigo = ?,
                @tipo = ?,
                @id_usuario = ?,
                @origen_entidad = ?,
                @direccion_postal = ?,
                @id_adm_p_comunas_postal = ?,
                @id_pro_p_paises = ?,
                @direccion_extranjera_estado = ?,
                @direccion_extranjera_zip = ?,
                @id_adm_p_listas_precios = ?,
                @id_pro_p_labores = ?,
                @id_pro_p_turnos = ?,
                @id_adm_p_centroscosto = ?",
            [
                0,
                $normalizedRut,
                $data['razon_social'] ?? '',
                $data['sucursal'] ?? '',
                $data['tipo_sucursal'] ?? 'Fisica',
                $data['nombre_sucursal'] ?? '',
                $data['direccion'] ?? '',
                $data['id_adm_comunas'] ?? null,
                0,
                $data['codigo'] ?? '',
                $data['tipo'] ?? 'Productor',
                0,
                'NACIONAL',
                '',
                0,
                0,
                '',
                '',
                0,
                0,
                0,
                0,
            ]
        );

        $fallback = DB::connection('sqlsrv')
            ->table('ADM_P_Entidades')
            ->select(['id'])
            ->whereRaw("REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '') = ?", [$normalizedRut])
            ->where('nombre_sucursal', $data['nombre_sucursal'] ?? null)
            ->orderByDesc('id')
            ->first();
        Log::debug('Entity created', ['entity_id' => $fallback?->id ?? null]);
        return $fallback ? (int) $fallback->id : 0;
    }

    private function updateEntityCsg(int $entityId, string $csg, string $predio, string $direccion, string $razonSocial): void
    {
        Log::debug('Updating entity', ['entity_id' => $entityId, 'csg' => $csg]);
        DB::connection('sqlsrv')
            ->table('ADM_P_Entidades')
            ->where('id', $entityId)
            ->update([
                'codigo_sag' => $csg,
                'CSG' => $csg,
                'nombre_sucursal' => $this->buildNombreSucursal($csg, $predio, $razonSocial),
                'direccion' => $direccion,
            ]);
    }

    private function createCentrosCosto(array $results, string $razonSocial, array $sagItems): array
    {
        $activeSagItems = array_values(array_filter($sagItems, function ($item) {
            $status = strtoupper((string) ($item['status'] ?? ''));
            return $status === 'ACTIVO';
        }));

        $byCsg = [];
        foreach ($activeSagItems as $item) {
            $csg = (string) ($item['csg_code'] ?? '');
            if ($csg === '') {
                continue;
            }
            $byCsg[$csg] = $item;
        }

        $variedadNames = [];
        $variedadLabels = [];
        foreach ($activeSagItems as $item) {
            $csg = $item['csg_code'] ?? null;
            foreach (($item['especie_variedades'] ?? []) as $entry) {
                $variedad = trim((string) ($entry['variedad'] ?? ''));
                if ($variedad === '' && ! empty($entry['raw'])) {
                    $parts = preg_split('/\s*[\-–—]\s*/u', (string) $entry['raw'], 2);
                    $variedad = isset($parts[1]) ? trim($parts[1]) : '';
                }
                if ($variedad === '') {
                    continue;
                }
                $variedadNames[] = $variedad;
                $variedadLabels[$csg][$variedad] = $entry['raw'] ?? $variedad;
            }
        }

        $variedadNames = collect($variedadNames)->unique()->values();
        $variedadMap = [];

        Log::debug('variedades', ['names' => $variedadNames->all()]);
        if ($variedadNames->isNotEmpty()) {
            $rows = DB::connection('sqlsrv')
                ->table('PRO_P_Variedades')
                ->select(['id', 'nombre'])
                ->whereIn(DB::raw('LTRIM(RTRIM(LOWER(nombre)))'), $variedadNames->map(fn ($name) => mb_strtolower(trim($name)))->all())
                ->get();
            foreach ($rows as $row) {
                $variedadMap[mb_strtolower(trim((string) $row->nombre))] = [
                    'id' => $row->id,
                    'nombre' => $row->nombre,
                ];
            }
        }

        $created = [];
        $skipped = [];
        $missing = [];

        foreach ($results as $result) {
            $csg = $result['csg'];
            $entityId = $result['entity_id'];
            if (! $entityId) {
                continue;
            }
            $item = $byCsg[$csg] ?? null;
            if (! $item) {
                continue;
            }
            $existingCodes = $this->getExistingCentroCostoCodes($csg);
            $nextSeq = $this->nextCentroCostoSeq($existingCodes, $csg);

            $variedades = collect($item['especie_variedades'] ?? [])
                ->map(function ($entry) {
                    $variedad = trim((string) ($entry['variedad'] ?? ''));
                    if ($variedad === '' && ! empty($entry['raw'])) {
                        $parts = preg_split('/\s*[\-–—]\s*/u', (string) $entry['raw'], 2);
                        $variedad = isset($parts[1]) ? trim($parts[1]) : '';
                    }
                    return $variedad;
                })
                ->filter()
                ->unique()
                ->values();

            foreach ($variedades as $variedad) {
                $variedadKey = mb_strtolower($variedad);
                $variedadRow = $variedadMap[$variedadKey] ?? null;
                if (! $variedadRow) {
                    $missing[] = ['csg' => $csg, 'variedad' => $variedad];
                    continue;
                }

                $exists = DB::connection('sqlsrv')
                    ->table('ADM_P_CentrosCosto')
                    ->where('id_adm_p_entidades', $entityId)
                    ->where('id_pro_p_variedades', $variedadRow['id'])
                    ->exists();

                if ($exists) {
                    $skipped[] = ['csg' => $csg, 'variedad' => $variedad];
                    continue;
                }

                $codigo = $this->nextAvailableCentroCostoCode($csg, $existingCodes, $nextSeq);
                $nextSeq++;
                $existingCodes[] = $codigo;
                $label = $variedadLabels[$csg][$variedad] ?? $variedad;
                $nombre = trim($razonSocial.' - '.$label);

                $procResult = $this->createCentroCostoViaProc([
                    'id_adm_p_entidades' => $entityId,
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'tipo_distribucion' => 1,
                    'tipo_centrocosto' => 3,
                    'id_pro_p_predios' => 10,
                    'id_pro_p_cuarteles' => 30,
                    'id_pro_p_variedades' => $variedadRow['id'],
                    'id_adm_p_administraciones' => 1,
                    'id_adm_p_areas_negocio' => 1,
                    'id_adm_p_unidad_DistribuirPor' => 0,
                ]);

                if (($procResult['error'] ?? '') !== 'OK' || empty($procResult['id_resultado'])) {
                    $missing[] = [
                        'csg' => $csg,
                        'variedad' => $variedad,
                        'error' => $procResult['error'] ?? 'Sin respuesta',
                    ];
                    Log::warning('Centro de costo no creado', [
                        'csg' => $csg,
                        'variedad' => $variedad,
                        'codigo' => $codigo,
                        'entity_id' => $entityId,
                        'result' => $procResult,
                    ]);
                } else {
                    $created[] = ['csg' => $csg, 'variedad' => $variedad, 'codigo' => $codigo];
                }
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'missing' => $missing,
        ];
    }

    private function createCentroCostoViaProc(array $data): array
    {
        Log::debug('Creating centro de costo', ['data' => $data]);
        DB::connection('sqlsrv')->statement(
            "EXEC ADM_P_CentrosCosto_Grabar
                @id = ?,
                @id_adm_p_entidades = ?,
                @codigo = ?,
                @nombre = ?,
                @tipo_distribucion = ?,
                @tipo_centrocosto = ?,
                @id_pro_p_predios = ?,
                @id_pro_p_cuarteles = ?,
                @id_pro_p_variedades = ?,
                @cantidad_ha = ?,
                @id_pkg_p_grupos_proceso = ?,
                @fecha_cosecha = ?,
                @id_pro_p_sectores_riego = ?,
                @id_adm_p_administraciones = ?,
                @id_adm_p_areas_negocio = ?,
                @subtipo_centrocosto = ?,
                @id_adm_p_Unidad = ?,
                @SDP = ?,
                @id_Usuario = ?,
                @Cp1 = ?,
                @Cp2 = ?,
                @Cp3 = ?,
                @cp4 = ?,
                @inversion = ?,
                @codigo_conta = ?,
                @activo = ?,
                @distribuir_Por = ?",
            [
                0,
                $data['id_adm_p_entidades'] ?? 0,
                $data['codigo'] ?? '',
                $data['nombre'] ?? '',
                $data['tipo_distribucion'] ?? 1,
                $data['tipo_centrocosto'] ?? 3,
                $data['id_pro_p_predios'] ?? 10,
                $data['id_pro_p_cuarteles'] ?? 30,
                $data['id_pro_p_variedades'] ?? 0,
                0,
                0,
                '',
                0,
                $data['id_adm_p_administraciones'] ?? 1,
                $data['id_adm_p_areas_negocio'] ?? 1,
                '',
                0,
                '',
                0,
                '',
                '',
                '',
                '',
                0,
                '',
                1,
                (string) ($data['id_adm_p_unidad_DistribuirPor'] ?? 0),
            ]
        );

        $created = DB::connection('sqlsrv')
            ->table('ADM_P_CentrosCosto')
            ->select(['id'])
            ->where('codigo', $data['codigo'] ?? '')
            ->orderByDesc('id')
            ->first();

        return [
            'error' => $created ? 'OK' : 'NO_RESULT',
            'id_resultado' => $created?->id ?? null,
        ];
    }

    private function nextCentroCostoSeq(array $existingCodes, string $csg): int
    {
        $max = 0;
        foreach ($existingCodes as $code) {
            if (! str_starts_with($code, $csg.'-')) {
                continue;
            }
            $suffix = substr($code, strlen($csg) + 1);
            $suffix = preg_replace('/\D/', '', (string) $suffix);
            if ($suffix === '') {
                continue;
            }
            $num = (int) $suffix;
            if ($num > $max) {
                $max = $num;
            }
        }

        return $max + 1;
    }

    private function nextAvailableCentroCostoCode(string $csg, array $existingCodes, int $startSeq): string
    {
        $seq = $startSeq;
        $existing = array_map('strtoupper', $existingCodes);
        do {
            $codigo = $csg.'-'.str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
            $seq++;
        } while (in_array(strtoupper($codigo), $existing, true));

        return $codigo;
    }

    private function getExistingCentroCostoCodes(string $csg): array
    {
        return DB::connection('sqlsrv')
            ->table('ADM_P_CentrosCosto')
            ->where('codigo', 'like', $csg.'-%')
            ->pluck('codigo')
            ->all();
    }

    private function buildNombreSucursal(string $csg, string $predio, string $razonSocial): string
    {
        $predio = trim($predio);
        $suffix = $predio !== '' ? $predio : $razonSocial;
        return trim($csg.' - '.$suffix);
    }

    private function addressSimilarity(string $a, string $b): int
    {
        $a = $this->normalizeText($a);
        $b = $this->normalizeText($b);
        if ($a === '' || $b === '') {
            return 0;
        }

        similar_text($a, $b, $percent);
        return (int) round($percent);
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', (string) $value);
        return trim($value);
    }

    public function updateCentroCostoSdp(string $csg, string $variedad, string $sdp): array
    {
        $csg = trim($csg);
        $variedad = trim($variedad);

        if ($csg === '' || $variedad === '') {
            return [
                'updated' => 0,
                'variedad_id' => null,
                'error' => 'CSG o variedad vacíos.',
            ];
        }

        static $variedadCache = [];
        $key = mb_strtolower($variedad);
        $variedadRow = $variedadCache[$key] ?? null;

        if (! $variedadRow) {
            $variedadRow = DB::connection('sqlsrv')
                ->table('PRO_P_Variedades')
                ->select(['id', 'nombre'])
                ->whereRaw('LTRIM(RTRIM(LOWER(nombre))) = ?', [$key])
                ->first();

            if (! $variedadRow) {
                $variedadRow = DB::connection('sqlsrv')
                    ->table('PRO_P_Variedades')
                    ->select(['id', 'nombre'])
                    ->whereRaw('LOWER(nombre) LIKE ?', ['%'.$key.'%'])
                    ->first();
            }

            if ($variedadRow) {
                $variedadCache[$key] = $variedadRow;
            }
        }

        if (! $variedadRow) {
            return [
                'updated' => 0,
                'variedad_id' => null,
                'error' => 'Variedad no encontrada en SQLSRV.',
            ];
        }

        $updated = DB::connection('sqlsrv')
            ->table('ADM_P_CentrosCosto')
            ->where('id_pro_p_variedades', $variedadRow->id)
            ->where('codigo', 'like', $csg.'%')
            ->update(['SDP' => $sdp]);

        return [
            'updated' => (int) $updated,
            'variedad_id' => (int) $variedadRow->id,
            'error' => null,
        ];
    }
}
