<?php

namespace App\Services\Garces\PalletBox;

use App\Services\Garces\GarcesCodeDictionaryService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GarcesPalletBoxService
{
    public function __construct(
        private readonly GarcesCodeDictionaryService $dictionary,
        private readonly Normalizer $normalizer,
        private readonly SqlServerReader $reader,
        private readonly SyncState $state
    ) {}

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

        $endpoint = rtrim($baseUrl, '/').'/api/PalletBoxSupplier/RegisterBoxes';

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
     * Construye los payloads que se enviarían, sin enviarlos ni registrar
     * faltantes de diccionario (no produce efectos secundarios en BD).
     *
     * @return array{endpoint:?string,tolerance:float,count:int,boxes:int,pallets:array<int,array{folio:string,payload:array<string,mixed>}>}
     */
    public function preview(?string $folio = null): array
    {
        $config = Config::get('services.garces_pallet_box') ?? [];
        $baseUrl = (string) ($config['base_url'] ?? '');

        $folio = $folio !== null ? trim($folio) : null;
        $items = $this->buildItems($folio === '' ? null : $folio, false);

        $boxes = 0;

        foreach ($items as $item) {
            $boxes += count($item['payload']['cajas'] ?? []);
        }

        return [
            'endpoint' => $baseUrl !== '' ? rtrim($baseUrl, '/').'/api/PalletBoxSupplier/RegisterBoxes' : null,
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
        $normalized = collect($this->normalizer->normalize($cabeceras, $detalles))->take($maxPallets);

        $items = [];

        foreach ($normalized as $item) {
            $items[] = [
                'folio' => (string) $item['folio'],
                'payload' => $this->buildPayload($item, $isExportDefault, $tolerance, $recordMissing),
            ];
        }

        return $items;
    }

    private function buildPayload(array $item, int $isExportDefault, float $tolerance, bool $recordMissing = true): array
    {
        $cab = $item['cabecera'] ?? (object) [];
        $defaults = $item['defaults'] ?? [];

        $codigoBarra = trim((string) ($cab->folio ?? $item['folio']));
        $translated = $this->translatePallet($item, $recordMissing);

        return [
            'pallets' => [
                'folio' => $item['folio'],
                'fechaProduccion' => $item['fecha_produccion'],
                'tipoTarja' => (string) ($defaults['tipo_tarja'] ?? ''),
                'especie' => $translated['especie'] ?? (string) ($cab->id_especie ?? ($cab->c_especie ?? '')),
                'variedad' => $translated['variedad'] ?? (string) ($cab->id_variedad ?? ($cab->c_variedad ?? '')),
                'envase' => $translated['envase'] ?? (string) ($cab->id_contenedor ?? ($cab->c_contenedor ?? '')),
                'embalaje' => $translated['embalaje'] ?? (string) ($cab->id_embalaje ?? ($cab->c_embalaje ?? '')),
                'etiqueta' => $translated['etiqueta'] ?? (string) ($cab->id_etiqueta ?? ($cab->c_etiqueta ?? '')),
                'tipoFruta' => (string) ($defaults['tipo_fruta'] ?? ''),
                'tipoPallet' => (string) ($defaults['tipo_pallet'] ?? ''),
                'categoria' => $translated['categoria'] ?? (string) ($cab->id_categoria ?? ($cab->c_categoria ?? '')),
                'isExport' => $isExportDefault,
                'cajasTotal' => $item['cantidad_cajas'],
                'kilosTotal' => round((float) $item['peso_neto_total'], 3),
                'subsidiary' => (string) ($defaults['subsidiary_garces'] ?? ($cab->id_empresa ?? ($cab->c_exportadora ?? ''))),
                'season' => (string) ($defaults['season_garces'] ?? ''),
            ],
            'detail' => $this->mapBoxes($item['detalles'] ?? []),
        ];
    }

    private function mapBoxes(array $detalles): array
    {||||
        $result = [];

        foreach ($detalles as $det) {
            $ncaja = is_object($det) ? ($det->idCaja ?? ($det->ncaja ?? ($det->id_pkg_stock_cajas ?? ($det->NumeroCaja ?? '')))) : ($det['idCaja'] ?? ($det['ncaja'] ?? ($det['id_pkg_stock_cajas'] ?? ($det['NumeroCaja'] ?? ''))));
            $peso = is_object($det) ? ($det->kilos ?? ($det->Peso_medido ?? ($det->peso_medido ?? ($det->PesoMedido ?? 0)))) : ($det['kilos'] ?? ($det['Peso_medido'] ?? ($det['peso_medido'] ?? ($det['PesoMedido'] ?? 0))));
            $lote = is_object($det) ? ($det->lote ?? ($det->recepciones_trazabilidad ?? ($det->lote_recepcion ?? ($det->loter_unitec ?? '')))) : ($det['lote'] ?? ($det['recepciones_trazabilidad'] ?? ($det['lote_recepcion'] ?? ($det['loter_unitec'] ?? ''))));
            $numeroProceso = is_object($det) ? ($det->numeroProceso ?? ($det->n_g_proceso ?? '')) : ($det['numeroProceso'] ?? ($det['n_g_proceso'] ?? ''));
            $productorReal = is_object($det) ? ($det->productorReal ?? ($det->c_productor_original ?? '')) : ($det['productorReal'] ?? ($det['c_productor_original'] ?? ''));
            $productor = is_object($det) ? ($det->productor ?? ($det->c_productor ?? '')) : ($det['productor'] ?? ($det['c_productor'] ?? ''));
            $variedadReal = is_object($det) ? ($det->variedadReal ?? ($det->id_variedad ?? '')) : ($det['variedadReal'] ?? ($det['id_variedad'] ?? ''));
            $variedadComercial = is_object($det) ? ($det->variedadComercial ?? ($det->id_variedad ?? '')) : ($det['variedadComercial'] ?? ($det['id_variedad'] ?? ''));
            $fechaEmbalaje = is_object($det) ? ($det->fechaEmbalaje ?? ($det->fecha_produccion ?? '')) : ($det['fechaEmbalaje'] ?? ($det['fecha_produccion'] ?? ''));
            $calibre = is_object($det) ? ($det->calibre ?? ($det->id_calibre ?? '')) : ($det['calibre'] ?? ($det['id_calibre'] ?? ''));
            $clasificacion = is_object($det) ? ($det->clasificacion ?? '') : ($det['clasificacion'] ?? '');
            $categoria = is_object($det) ? ($det->categoria ?? ($det->id_categoria ?? '')) : ($det['categoria'] ?? ($det['id_categoria'] ?? ''));
            $etiqueta = is_object($det) ? ($det->etiqueta ?? ($det->id_etiqueta ?? '')) : ($det['etiqueta'] ?? ($det['id_etiqueta'] ?? ''));

            $result[] = [
                'numeroCaja' => is_numeric($ncaja) ? (int) $ncaja : $ncaja,
                'pesoMedido' => is_numeric($peso) ? (float) $peso : $peso,
                'lote' => $lote,
                'numeroProceso' => $numeroProceso,
                'productorReal' => $productorReal,
                'productor' => $productor,
                'variedadReal' => $variedadReal,
                'variedadComercial' => $variedadComercial,
                'fechaEmbalaje' => $fechaEmbalaje,
                'calibre' => $calibre,
                'cajas' => 1,
                'kilos' => is_numeric($peso) ? (float) $peso : $peso,
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
            GarcesCodeDictionaryService::TYPE_ENVASE => (string) ($cab->id_contenedor ?? ($cab->c_contenedor ?? '')),
            GarcesCodeDictionaryService::TYPE_EMBALAJE => (string) ($cab->id_embalaje ?? ($cab->c_embalaje ?? '')),
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
