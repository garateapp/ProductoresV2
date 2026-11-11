<?php

namespace App\Services\Garces\PalletBox;

use App\Services\Garces\GarcesCodeDictionaryService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class GarcesPalletBoxService
{
    public function __construct(
        private readonly GarcesCodeDictionaryService $dictionary,
        private readonly Normalizer $normalizer,
        private readonly SqlServerReader $reader,
        private readonly SyncState $state
    ) {}
       public function connection(): string
    {
        return (string) config('services.termo.auto_consumption.conexion', 'sqlsrv');
    }
    /**
     * @return array{sent:int,rejected:int,errors:int,details:array<int,mixed>}
     */
    public function syncPending(int $batchSize = 100, ?\DateTimeInterface $hasta = null): array
    {
        $empty = ['sent' => 0, 'rejected' => 0, 'errors' => 0, 'details' => []];

        $config = Config::get('services.garces_pallet_box');
        $enabled = (bool) ($config['enabled'] ?? true);

        if (! $enabled) {
            return $empty;
        }

        $baseUrl = (string) ($config['base_url'] ?? '');
        $apiKey = (string) ($config['api_key'] ?? '');

        if ($baseUrl === '' || $apiKey === '') {
            Log::warning('GarcesPalletBoxService: base_url o api_key no configurados.');

            return $empty;
        }

        $items = $this->buildItems(null, true);

        if ($items === []) {
            return $empty;
        }

        $endpoint = $this->endpoint();

        if ($endpoint === null) {
            Log::warning('GarcesPalletBoxService: base_url o api_key no configurados.');

            return $empty;
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'x-api-key' => $apiKey,
        ];

        $sent = 0;
        $rejected = 0;
        $errors = 0;
        $details = [];

        foreach ($items as $item) {
            $payload = $item['payload'];

            try {
                $response = Http::withHeaders($headers)
                    ->timeout((int) ($config['timeout'] ?? 60))
                    ->post($endpoint, $payload);

                $body = $response->json() ?? [];

                if (($body['isSucess'] ?? false) || ($body['isSuccess'] ?? false)) {
                    $sent++;
                    $details[] = ['folio' => $item['folio'], 'status' => 'sent'];
                } else {
                    $rejected++;
                    $details[] = [
                        'folio' => $item['folio'],
                        'status' => 'rejected',
                        'rejected' => $body['rejected'] ?? ($body['Rejected'] ?? []),
                    ];
                }
            } catch (\Throwable $e) {
                $errors++;
                $details[] = [
                    'folio' => $item['folio'],
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
                Log::error('GarcesPalletBoxService: error al enviar', [
                    'folio' => $item['folio'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'sent' => $sent,
            'rejected' => $rejected,
            'errors' => $errors,
            'details' => $details,
        ];
    }

    /**
     * URL de envío de Sispro (RegisterBoxes). GARCES_PALLET_BOX_BASE_URL puede venir
     * con o sin la ruta del endpoint; se evita duplicarla.
     */
    public function endpoint(): ?string
    {
        $baseUrl = trim((string) (Config::get('services.garces_pallet_box.base_url') ?? ''));

        if ($baseUrl === '') {
            return null;
        }

        if (str_contains($baseUrl, '/api/PalletBoxSupplier/RegisterBoxes')) {
            return rtrim($baseUrl, '/');
        }

        return rtrim($baseUrl, '/').'/api/PalletBoxSupplier/RegisterBoxes';
    }

    /**
     * Payloads RegisterBoxes de los folios indicados, sin enviarlos y sin
     * registrar faltantes de diccionario.
     *
     * @return array<int,array{folio:string,payload:array<string,mixed>}>
     */
    public function payloadsForFolios(array $folios): array
    {
        
        $items = [];

        foreach (array_values(array_unique(array_map('trim', array_map('strval', $folios)))) as $folio) {
            if ($folio === '') {
                continue;
            }

            foreach ($this->buildItems($folio, false) as $item) {
                $items[] = $item;
            }
        }
        Log::info('GarcesPalletBoxService: payloadsForFolios', ['folios' => $items]);
        return $items;
    }

    /**
     * Un solo payload RegisterBoxes con los pallets de todos los folios indicados
     * (los folios seleccionados viajan juntos en el mismo JSON).
     *
     * @param  array<int,array{folio:string,payload:array<string,mixed>}>  $items
     * @return array<string,mixed>
     */
    public function mergeItems(array $items): array
    {
        $config = Config::get('services.garces_pallet_box') ?? [];
        $maxPallets = (int) ($config['max_pallets_per_shipment'] ?? 50);

        $pallets = [];
        $codeSubsidiary = '';
        $codeSeason = '';

        foreach ($items as $item) {
            $payload = $item['payload'] ?? [];

            if ($codeSubsidiary === '') {
                $codeSubsidiary = (string) ($payload['codeSubsidiary'] ?? '');
            }

            if ($codeSeason === '') {
                $codeSeason = (string) ($payload['codeSeason'] ?? '');
            }

            foreach ($payload['pallets'] ?? [] as $pallet) {
                $pallets[] = $pallet;
            }
        }

        return [
            'codeSubsidiary' => $codeSubsidiary,
            'codeSeason' => $codeSeason,
            'pallets' => array_slice($pallets, 0, max(1, $maxPallets)),
        ];
    }

    /**
     * Construye los payloads que se enviarían, sin enviarlos ni registrar
     * faltantes de diccionario (no produce efectos secundarios en BD).
     *
     * @return array{endpoint:?string,tolerance:float,count:int,boxes:int,pallets:array<int,array{folio:string,payload:array<string,mixed>}>}
     */
    public function preview(?string $folio = null): array
    {
        $config = Config::get('services.garces_pallet_box') ?? [];

        $folio = $folio !== null ? trim($folio) : null;
        $items = $this->buildItems($folio === '' ? null : $folio, false);

        $boxes = 0;

        foreach ($items as $item) {
            foreach ($item['payload']['pallets'] ?? [] as $pallet) {
                $boxes += count($pallet['detail'] ?? []);
            }
        }

        return [
            'endpoint' => $this->endpoint(),
            'tolerance' => (float) ($config['kilos_tolerance'] ?? 0.01),
            'count' => count($items),
            'boxes' => $boxes,
            'pallets' => $items,
        ];
    }

    /**
     * @return array<int,array{folio:string,payload:array<string,mixed>}>
     */
    private function buildItems(?string $folio, bool $recordMissing): array
    { 
       
        $config = Config::get('services.garces_pallet_box') ?? [];

        $maxPallets = (int) ($config['max_pallets_per_shipment'] ?? 50);
        $maxBoxes = (int) ($config['max_boxes_per_shipment'] ?? 5000);
        $tolerance = (float) ($config['kilos_tolerance'] ?? 0.01);
        $isExportDefault = (int) ($config['is_export_default'] ?? 1);

        $cabeceras = $this->reader->getCabeceras($folio !== null ? [$folio] : []);
        $folios = $cabeceras->pluck('folio')->map(fn ($f) => trim((string) $f))->filter()->unique()->take($maxPallets)->values();

        if ($folios->isEmpty()) {
            return [];
        }
      
        $detalles = $this->reader->getDetalles($folios->all())->take($maxBoxes);
        $normalized = collect($this->normalizer->normalize($cabeceras, $detalles));

        // Un solo header por folio: si hay múltiples registros de cabecera para
        // el mismo folio, se combinan todos los detalles bajo ese header.
        $grouped = $normalized->groupBy(fn ($item) => (string) ($item['folio'] ?? ''));

        $items = [];

        foreach ($grouped as $folioKey => $folioItems) {
            $first = $folioItems->first();
            if (! $first) {
                continue;
            }

            // Un solo header por folio. Los detalles ya vienen agrupados por folio
            // en el Normalizer, así que se toman de una sola cabecera para evitar
            // duplicar cajas cuando getCabeceras devuelve varios registros del mismo folio.
            $allDetalles = $first['detalles'] ?? [];

            $combined = [
                'folio' => $folioKey,
                'fecha_produccion' => $first['fecha_produccion'],
                'especie_interna' => $first['especie_interna'],
                'variedad_interna' => $first['variedad_interna'],
                'cantidad_cajas' => $first['cantidad_cajas'],
                'peso_neto_total' => $first['peso_neto_total'],
                'subsidiary' => $first['subsidiary'],
                'season' => $first['season'],
                'code_subsidiary' => $first['code_subsidiary'],
                'code_season' => $first['code_season'],
                'defaults' => $first['defaults'],
                'cabecera' => $first['cabecera'],
                'detalles' => $allDetalles,
            ];

            $items[] = [
                'folio' => $folioKey,
                'payload' => $this->buildPayload($combined, $isExportDefault, $tolerance, $recordMissing),
            ];
        }

        return $items;
    }

    private function buildPayload(array $item, int $isExportDefault, float $tolerance, bool $recordMissing = true): array
    {
       
        $cab = $item['cabecera'] ?? (object) [];
        $defaults = $item['defaults'] ?? [];
        $config = Config::get('services.garces_pallet_box') ?? [];

        $codigoBarra = trim((string) ($cab->folio ?? $item['folio']));
        $translated = $this->translatePallet($item, $recordMissing);
        $tipoTarja = '1';
        $folioUpper = strtoupper($codigoBarra);
        switch (true) {
            case str_ends_with($folioUpper, 'T'):
                $tipoTarja = '2'; // saldo
                break;
            case ctype_digit($codigoBarra):
                $tipoTarja = '1'; // folio numérico
                break;
            default:
                $tipoTarja = '1';
                break;
        }
        $tipoPallet = '4';
        $folioUpper = strtoupper($codigoBarra);
        switch (true) {
            case str_ends_with($folioUpper, 'T'):
                if($cab->id_categoria==12){
                    $tipoPallet='9';
                    $tipoTarja='';
                }else{
                   $tipoTarja = '2'; // saldo
                $tipoPallet='7';
                }
                
                
                break;
            case ctype_digit($codigoBarra):
                $tipoTarja = '1'; // folio numérico
                $tipoPallet='4';
                break;
            default:
                $tipoTarja = '';
                $tipoPallet='9';
                break;
        }
        
        // Un solo header con todas las cajas de los registros del folio/tarja.
        $mappedBoxes = $this->mapBoxes($item['detalles'] ?? [], $recordMissing);
        $cajasTotal = count($mappedBoxes);
        $isExport=1;
        switch($cab->id_categoria){
            case 4:
                $isExport=1;
                break;
            case 5:
                $isExport=1;
                break;
            case 12:
                $isExport=1;
                break;
            default:
                $isExport=2;
                break;
        }
     
        $kilosTotal = array_reduce($mappedBoxes, static function ($acc, $box) {
            return $acc + (float) ($box['kilos'] ?? 0);
        }, 0.0);
        $envase='';
        $embalaje='';
        if(str_contains($cab->c_embalaje,'-')){
            $envase=explode('-', $cab->c_embalaje)[1] ?? $cab->c_envase??'' ;
            $embalaje=explode('-', $cab->c_embalaje)[0] ?? $cab->c_envase??'' ;
        }else{
            $envase=$cab->c_envase??'';
            $embalaje=$cab->c_embalaje??'';
        }
        return [

                'codeSubsidiary' => (string) (($config['code_subsidiary'] ?? null) ?: ($defaults['subsidiary_garces'] ?? ($cab->id_empresa ?? ($cab->c_exportadora ?? '')))),
                'codeSeason' => (string) (($config['code_season'] ?? null) ?: ($defaults['season_garces'] ?? '')),

            'pallets' => [
                [
                    'header' => [
                        'folio' => $item['folio'],
                        'fechaTarja' => $this->formatDate($item['fecha_produccion'] ?? null),
                        'tipoTarja' => (string) $tipoTarja,
                        'especie' => "21",//$translated['especie'] ?? (string) ($cab->id_especie ?? ($cab->c_especie ?? '')),
                        'variedad' => $translated['variedad'] ?? (string) ($cab->id_variedad ?? ($cab->c_variedad ?? '')),
                        'envase' => $envase,
                        'embalaje' => $embalaje,
                        'etiqueta' => $translated['etiqueta'] ?? (string) ($cab->id_etiqueta ?? ($cab->c_etiqueta ?? '')),
                        'tipoFruta' => (string) ($defaults['tipo_fruta'] ?? '1'),
                        'tipoPallet' => (string) $tipoPallet,
                        'categoria' => $translated['categoria'] ?? (string) ($cab->id_categoria ?? ($cab->c_categoria ?? '')),
                        'isExport' => $isExport,
                        'cajasTotal' => $cajasTotal,
                        'kilosTotal' => round((float) $kilosTotal, 3),
                    ],
                    'detail' => $mappedBoxes,
                ],
            ],
        ];
    }

    private function mapBoxes(array $detalles, bool $recordMissing = true): array
    {
        $conn = $this->connection();
        $result = [];
        $nota_calidad='';

            //  $nota_calidad = DB::connection($conn)->table('V_PKG_Recepcion_FG')
            //      ->where('numero_g_recepcion', $detalles[0]->n_g_recepcion)
            //      ->select('nota_calidad')
            //      ->first();



        foreach ($detalles as $det) {
            $ncaja = is_object($det) ? ($det->idCaja ?? ($det->ncaja ?? ($det->id_pkg_stock_cajas ?? ($det->NumeroCaja ?? '')))) : ($det['idCaja'] ?? ($det['ncaja'] ?? ($det['id_pkg_stock_cajas'] ?? ($det['NumeroCaja'] ?? ''))));
            $peso = is_object($det) ? ($det->kilos ?? ($det->Peso_medido ?? ($det->peso_medido ?? ($det->PesoMedido ?? 0)))) : ($det['kilos'] ?? ($det['Peso_medido'] ?? ($det['peso_medido'] ?? ($det['PesoMedido'] ?? 0))));
            $lote = is_object($det) ? ($det->n_g_recepcion ??  ''):'';
            $lote = trim(explode(',', (string) $lote)[0]);
            $nota_calidad = DB::connection($conn)->table('V_PKG_Recepcion_FG')
                ->where('numero_g_recepcion', $lote)
                ->select('nota_calidad')
                ->first();
            $numeroProceso = is_object($det) ? ($det->numeroProceso ?? ($det->n_g_proceso ?? '')) : ($det['numeroProceso'] ?? ($det['n_g_proceso'] ?? ''));
            $productorReal = is_object($det) ? ($det->productorReal ?? ($det->c_productor_original ?? '')) : ($det['productorReal'] ?? ($det['c_productor_original'] ?? ''));
            $productor = is_object($det) ? ($det->productor ?? ($det->c_productor ?? '')) : ($det['productor'] ?? ($det['c_productor'] ?? ''));
            $variedadRealRaw = is_object($det) ? ($det->variedadReal ?? ($det->id_variedad ?? '')) : ($det['variedadReal'] ?? ($det['id_variedad'] ?? ''));
            $variedadComercialRaw = is_object($det) ? ($det->variedadComercial ?? ($det->id_variedad ?? '')) : ($det['variedadComercial'] ?? ($det['id_variedad'] ?? ''));
            $fechaEmbalaje = is_object($det) ? ($det->fechaEmbalaje ?? ($det->fecha_produccion ?? '')) : ($det['fechaEmbalaje'] ?? ($det['fecha_produccion'] ?? ''));
            $calibreRaw = is_object($det) ? ($det->calibre ?? ($det->c_calibre ?? '')) : ($det['calibre'] ?? ($det['id_calibre'] ?? ''));
            $clasificacion = is_object($det) ? ($det->nota_calidad ?? $nota_calidad) : ($det['nota_calidad'] ?? '');
            $categoriaRaw = is_object($det) ? ($det->categoria ?? ($det->id_categoria ?? '')) : ($det['categoria'] ?? ($det['id_categoria'] ?? ''));
            $etiquetaRaw = is_object($det) ? ($det->etiqueta ?? ($det->id_etiqueta ?? '')) : ($det['etiqueta'] ?? ($det['id_etiqueta'] ?? ''));

            $translations = $this->dictionary->translateMany([
                GarcesCodeDictionaryService::TYPE_VARIEDAD => (string) $variedadRealRaw,
                GarcesCodeDictionaryService::TYPE_CATEGORIA => (string) $categoriaRaw,
                GarcesCodeDictionaryService::TYPE_ETIQUETA => (string) $etiquetaRaw,
                GarcesCodeDictionaryService::TYPE_CALIBRE => (string) $calibreRaw,
            ]);

            if ($recordMissing) {
                foreach ($translations as $tipo => $valor) {
                    if ($valor === null) {
                        $raw = match ($tipo) {
                            GarcesCodeDictionaryService::TYPE_VARIEDAD => (string) $variedadRealRaw,
                            GarcesCodeDictionaryService::TYPE_CATEGORIA => (string) $categoriaRaw,
                            GarcesCodeDictionaryService::TYPE_ETIQUETA => (string) $etiquetaRaw,
                            GarcesCodeDictionaryService::TYPE_CALIBRE => (string) $calibreRaw,
                            default => '',
                        };
                        $this->dictionary->missing($tipo, $raw === '' ? null : $raw);
                    }
                }
            }

            $variedadReal = $translations[GarcesCodeDictionaryService::TYPE_VARIEDAD] ?? (string) $variedadRealRaw;
            $variedadComercial = $translations[GarcesCodeDictionaryService::TYPE_VARIEDAD] ?? (string) $variedadComercialRaw;
            $categoria = $translations[GarcesCodeDictionaryService::TYPE_CATEGORIA] ?? (string) $categoriaRaw;
            $etiqueta = $translations[GarcesCodeDictionaryService::TYPE_ETIQUETA] ?? (string) $etiquetaRaw;
            $calibre = $translations[GarcesCodeDictionaryService::TYPE_CALIBRE] ?? (string) $calibreRaw;

            $result[] = [
                'idCaja' => is_numeric($ncaja) ? (int) $ncaja : $ncaja,
                'kilos' => is_numeric($peso) ? (float) $peso : $peso,
                'lote' => $lote,
                'numeroProceso' => $numeroProceso,
                'productorReal' => $productorReal,
                'productor' => $productor,
                'variedadReal' => $variedadReal,
                'variedadComercial' => $variedadComercial,
                'fechaEmbalaje' => $this->formatDate($fechaEmbalaje),
                'calibre' => $calibre,
                'cajas' => 1,
                'clasificacion' => $clasificacion,
                'categoria' => $categoria,
                'etiqueta' => $etiqueta,
            ];
        }

        return $result;
    }

    private function translatePallet(array $item, bool $recordMissing = true): array
    {
        $cab = $item['cabecera'] ?? (object) [];

        $translations = $this->dictionary->translateMany([
            GarcesCodeDictionaryService::TYPE_ESPECIE => (string) ($cab->id_especie ?? ($cab->c_especie ?? '')),
            GarcesCodeDictionaryService::TYPE_VARIEDAD => (string) ($cab->id_variedad ?? ($cab->c_variedad ?? '')),
            GarcesCodeDictionaryService::TYPE_CATEGORIA => (string) ($cab->id_categoria ?? ($cab->c_categoria ?? '')),
            GarcesCodeDictionaryService::TYPE_CALIBRE => (string) ($cab->id_calibre ?? ($cab->c_calibre ?? '')),
            GarcesCodeDictionaryService::TYPE_ETIQUETA => (string) ($cab->id_etiqueta ?? ($cab->c_etiqueta ?? '')),
            GarcesCodeDictionaryService::TYPE_ENVASE => (string) ($cab->c_contenedor ?? ($cab->c_contenedor ?? '')),
            GarcesCodeDictionaryService::TYPE_EMBALAJE => (string) ($cab->c_embalaje ?? ($cab->c_embalaje ?? '')),
            GarcesCodeDictionaryService::TYPE_CLASIFICACION => (string) ($cab->id_categoria ?? ($cab->c_categoria ?? '')),
        ]);

        if (! $recordMissing) {
            return $translations;
        }

        foreach ($translations as $tipo => $valor) {
            if ($valor === null) {
                $raw = $this->getRaw($cab, $tipo);
                $this->dictionary->missing($tipo, $raw === '' ? null : $raw);
            }
        }

        return $translations;
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d\TH:i:s');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function getRaw(object $cab, string $tipo): string
    {
        return match ($tipo) {
            GarcesCodeDictionaryService::TYPE_ESPECIE => (string) ($cab->id_especie ?? ($cab->c_especie ?? '')),
            GarcesCodeDictionaryService::TYPE_VARIEDAD => (string) ($cab->id_variedad ?? ($cab->c_variedad ?? '')),
            GarcesCodeDictionaryService::TYPE_CATEGORIA => (string) ($cab->id_categoria ?? ($cab->c_categoria ?? '')),
            GarcesCodeDictionaryService::TYPE_CALIBRE => (string) ($cab->id_calibre ?? ($cab->c_calibre ?? '')),
            GarcesCodeDictionaryService::TYPE_ETIQUETA => (string) ($cab->id_etiqueta ?? ($cab->c_etiqueta ?? '')),
            GarcesCodeDictionaryService::TYPE_ENVASE => (string) ($cab->id_contenedor ?? ($cab->c_contenedor ?? '')),
            GarcesCodeDictionaryService::TYPE_EMBALAJE => (string) ($cab->id_embalaje ?? ($cab->c_embalaje ?? '')),
            GarcesCodeDictionaryService::TYPE_CLASIFICACION => (string) ($cab->id_categoria ?? ($cab->c_categoria ?? '')),
            default => '',
        };
    }
}
