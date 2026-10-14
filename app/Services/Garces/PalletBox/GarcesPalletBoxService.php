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
        $config = Config::get('services.garces_pallet_box');
        $enabled = (bool) ($config['enabled'] ?? true);

        if (! $enabled) {
            return ['sent' => 0, 'rejected' => 0, 'errors' => 0, 'details' => []];
        }

        $baseUrl = (string) ($config['base_url'] ?? '');
        $apiKey = (string) ($config['api_key'] ?? '');

        if ($baseUrl === '' || $apiKey === '') {
            Log::warning('GarcesPalletBoxService: base_url o api_key no configurados.');

            return ['sent' => 0, 'rejected' => 0, 'errors' => 0, 'details' => []];
        }

        $maxPallets = (int) ($config['max_pallets_per_shipment'] ?? 50);
        $maxBoxes = (int) ($config['max_boxes_per_shipment'] ?? 5000);
        $tolerance = (float) ($config['kilos_tolerance'] ?? 0.01);
        $isExportDefault = (int) ($config['is_export_default'] ?? 1);

        $cabeceras = $this->reader->getCabeceras();
        $folios = $cabeceras->pluck('folio')->map(fn ($f) => trim((string) $f))->filter()->unique()->take($maxPallets)->values();

        if ($folios->isEmpty()) {
            return ['sent' => 0, 'rejected' => 0, 'errors' => 0, 'details' => []];
        }

        $detalles = $this->reader->getDetalles($folios->all());
        $detalles = $detalles->take($maxBoxes);

        $normalizedRaw = $this->normalizer->normalize($cabeceras, $detalles);
        $normalized = collect($normalizedRaw)->take($maxPallets);

        if ($normalized === []) {
            return ['sent' => 0, 'rejected' => 0, 'errors' => 0, 'details' => []];
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

        foreach ($normalized as $item) {
            $payload = $this->buildPayload($item, $isExportDefault, $tolerance);

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

    private function buildPayload(array $item, int $isExportDefault, float $tolerance): array
    {
        $cab = $item['cabecera'] ?? (object) [];
        $defaults = $item['defaults'] ?? [];

        $codigoBarra = trim((string) ($cab->folio ?? $item['folio']));
        $translated = $this->translatePallet($item);

        return [
            'pallet' => [
                'fechaProduccion' => $item['fecha_produccion'],
                'codigoBarras' => $codigoBarra,
                'folio' => $item['folio'],
                'especie' => $translated['especie'] ?? (string) ($cab->c_especie ?? ''),
                'variedad' => $translated['variedad'] ?? (string) ($cab->c_variedad ?? ''),
                'cantidadCajas' => $item['cantidad_cajas'],
                'pesoNetoTotal' => round((float) $item['peso_neto_total'], 3),
                'subsidiary' => (string) ($defaults['subsidiary_garces'] ?? ($cab->code_subsidiary ?? '')),
                'season' => (string) ($defaults['season_garces'] ?? ($cab->code_season ?? '')),
                'tipoTarja' => (string) ($defaults['tipo_tarja'] ?? ''),
                'tipoFruta' => (string) ($defaults['tipo_fruta'] ?? ''),
                'tipoPallet' => (string) ($defaults['tipo_pallet'] ?? ''),
                'isExport' => $isExportDefault,
                'clasificacion' => $translated['clasificacion'] ?? '',
                'categoria' => $translated['categoria'] ?? (string) ($cab->c_categoria ?? ''),
                'calibre' => $translated['calibre'] ?? (string) ($cab->c_calibre ?? ''),
                'etiqueta' => $translated['etiqueta'] ?? (string) ($cab->c_etiqueta ?? ''),
                'envase' => $translated['envase'] ?? (string) ($cab->c_contenedor ?? ''),
                'embalaje' => $translated['embalaje'] ?? (string) ($cab->c_embalaje ?? ''),
                'CP1' => (string) ($cab->CP1 ?? ''),
                'CP2' => (string) ($cab->CP2 ?? ''),
                'CP3' => (string) ($cab->CP3 ?? ''),
                'CP4' => (string) ($cab->CP4 ?? ''),
                'CP5' => (string) ($cab->CP5 ?? ''),
                'toleranciaKilos' => $tolerance,
            ],
            'cajas' => $this->mapBoxes($item['detalles'] ?? []),
        ];
    }

    private function mapBoxes(array $detalles): array
    {
        $result = [];

        foreach ($detalles as $det) {
            $result[] = [
                'numeroCaja' => is_object($det) ? ($det->ncaja ?? '') : ($det['ncaja'] ?? ''),
                'pesoMedido' => is_object($det) ? ($det->Peso_medido ?? ($det->peso_medido ?? 0)) : ($det['Peso_medido'] ?? ($det['peso_medido'] ?? 0)),
                'lote' => is_object($det) ? ($det->recepciones_trazabilidad ?? ($det->lote_recepcion ?? '')) : ($det['recepciones_trazabilidad'] ?? ($det['lote_recepcion'] ?? '')),
            ];
        }

        return $result;
    }

    private function translatePallet(array $item): array
    {
        $cab = $item['cabecera'] ?? (object) [];

        $translations = $this->dictionary->translateMany([
            GarcesCodeDictionaryService::TYPE_ESPECIE => (string) ($cab->c_especie ?? ''),
            GarcesCodeDictionaryService::TYPE_VARIEDAD => (string) ($cab->c_variedad ?? ''),
            GarcesCodeDictionaryService::TYPE_CATEGORIA => (string) ($cab->c_categoria ?? ''),
            GarcesCodeDictionaryService::TYPE_CALIBRE => (string) ($cab->c_calibre ?? ''),
            GarcesCodeDictionaryService::TYPE_ETIQUETA => (string) ($cab->c_etiqueta ?? ''),
            GarcesCodeDictionaryService::TYPE_ENVASE => (string) ($cab->c_contenedor ?? ''),
            GarcesCodeDictionaryService::TYPE_EMBALAJE => (string) ($cab->c_embalaje ?? ''),
            GarcesCodeDictionaryService::TYPE_CLASIFICACION => (string) ($cab->c_categoria ?? ''),
        ]);

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
            GarcesCodeDictionaryService::TYPE_ESPECIE => (string) ($cab->c_especie ?? ''),
            GarcesCodeDictionaryService::TYPE_VARIEDAD => (string) ($cab->c_variedad ?? ''),
            GarcesCodeDictionaryService::TYPE_CATEGORIA => (string) ($cab->c_categoria ?? ''),
            GarcesCodeDictionaryService::TYPE_CALIBRE => (string) ($cab->c_calibre ?? ''),
            GarcesCodeDictionaryService::TYPE_ETIQUETA => (string) ($cab->c_etiqueta ?? ''),
            GarcesCodeDictionaryService::TYPE_ENVASE => (string) ($cab->c_contenedor ?? ''),
            GarcesCodeDictionaryService::TYPE_EMBALAJE => (string) ($cab->c_embalaje ?? ''),
            GarcesCodeDictionaryService::TYPE_CLASIFICACION => (string) ($cab->c_categoria ?? ''),
            default => '',
        };
    }
}
