<?php

namespace App\Services;

use App\Models\ProduccionGarcesSyncState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProduccionGarcesSyncService
{
    private ?ProduccionGarcesSyncState $state = null;

    private string $endpoint;

    private string $apiKey;

    private int $batchSize;

    private bool $enabled;

    public function __construct()
    {
        $this->endpoint = (string) env('GARCES_SYNC_ENDPOINT', '');
        $this->apiKey = (string) env('GARCES_SYNC_API_KEY', '');
        $this->batchSize = (int) env('GARCES_SYNC_BATCH_SIZE', 100);
        $this->enabled = (bool) env('GARCES_SYNC_ENABLED', true);
    }

    public function execute(): array
    {
        if (! $this->enabled) {
            Log::info('ProduccionGarcesSync: deshabilitado por GARCES_SYNC_ENABLED=false');

            return ['status' => 'disabled', 'sent' => 0, 'failed' => 0];
        }

        if ($this->endpoint === '') {
            Log::error('ProduccionGarcesSync: GARCES_SYNC_ENDPOINT no configurado');

            return ['status' => 'error', 'message' => 'Endpoint no configurado', 'sent' => 0, 'failed' => 0];
        }

        $this->state = $this->getOrCreateState();
        $this->state->update(['status' => 'running', 'last_run_at' => now(), 'last_error' => null]);

        try {
            $records = $this->fetchNewRecords();

            if ($records->isEmpty()) {
                $this->state->update(['status' => 'completed']);

                Log::info('ProduccionGarcesSync: sin registros nuevos');

                return ['status' => 'completed', 'sent' => 0, 'failed' => 0];
            }

            $totalSent = 0;
            $totalFailed = 0;
            $batches = $records->chunk($this->batchSize);

            foreach ($batches as $batch) {
                $result = $this->sendBatch($batch);
                $totalSent += $result['sent'];
                $totalFailed += $result['failed'];

                if ($result['fatal']) {
                    break;
                }
            }

            $lastRecord = $records->last();
            $this->state->update([
                'status' => $totalFailed > 0 ? 'failed' : 'completed',
                'last_fecha_proceso' => $lastRecord->fecha_proceso,
                'last_numero_proceso' => $lastRecord->numero_proceso,
                'records_sent' => $this->state->records_sent + $totalSent,
                'records_failed' => $this->state->records_failed + $totalFailed,
            ]);

            Log::info("ProduccionGarcesSync: enviados={$totalSent}, fallidos={$totalFailed}, total_registros={$records->count()}");

            return ['status' => 'completed', 'sent' => $totalSent, 'failed' => $totalFailed];
        } catch (\Throwable $e) {
            $this->state->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            Log::error('ProduccionGarcesSync: '.$e->getMessage(), ['exception' => $e]);

            return ['status' => 'error', 'message' => $e->getMessage(), 'sent' => 0, 'failed' => 0];
        }
    }

    private function getOrCreateState(): ProduccionGarcesSyncState
    {
        $state = ProduccionGarcesSyncState::first();

        if (! $state) {
            $state = ProduccionGarcesSyncState::create([
                'status' => 'pending',
            ]);
        }

        return $state;
    }

    private function fetchNewRecords(): Collection
    {
        $viewName = (string) config('cuadratura.sqlsrv.views.completo', 'V_PKG_Produccion_Completo_XXX');
        $query = DB::connection('sqlsrv')
            ->table($viewName)
            ->select([
                'numero_proceso',
                DB::raw("'1' as destino_tarja"),
                'folio',
                'fecha_proceso',
                DB::raw("'1' as tipo_tarja"),
                'id_especie as especie',
                DB::raw("'' as envase"),
                'c_embalaje as embalaje',
                DB::raw("'1' as tipo_fruta"),
                DB::raw("'4' as tipo_pallet"),
                'c_altura as altura',
                DB::raw("'1' as plu"),
                'cantidad as cajas_total',
                'peso_neto as kilos_total',
                'lote_recepcion as lote',
                'id_productor',
                DB::raw("'1' as sitio_pro"),
                'n_variedad_proceso',
                'n_variedad',
                'n_productor_proceso',
                'fecha_proceso_sf as fec_embalaje',
                'n_calibre',
                'cantidad as cajas',
                'peso_neto as kilos',
                't_categoria',
                'n_categoria',
            ]);

        if ($this->state && $this->state->last_fecha_proceso) {
            $query->where('fecha_proceso', '>', $this->state->last_fecha_proceso);
        }

        $query->orderBy('fecha_proceso', 'asc')
            ->orderBy('numero_proceso', 'asc');

        return $query->get();
    }

    private function sendBatch(Collection $batch): array
    {
        $payload = $batch->map(fn ($row) => $this->mapRecord($row))->values()->all();

        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey !== '') {
                $headers['Authorization'] = 'Bearer '.$this->apiKey;
            }

            $response = Http::withHeaders($headers)
                ->timeout(60)
                ->post($this->endpoint, $payload);

            if ($response->successful()) {
                return ['sent' => $batch->count(), 'failed' => 0, 'fatal' => false];
            }

            Log::warning('ProduccionGarcesSync: POST devolvió '.$response->status(), [
                'body' => $response->body(),
                'batch_count' => $batch->count(),
            ]);

            return ['sent' => 0, 'failed' => $batch->count(), 'fatal' => false];
        } catch (\Throwable $e) {
            Log::error('ProduccionGarcesSync: error en POST: '.$e->getMessage());

            return ['sent' => 0, 'failed' => $batch->count(), 'fatal' => true];
        }
    }

    private function mapRecord(object $row): array
    {
        return [
            'numero_proceso' => $row->numero_proceso,
            'destino_tarja' => (string) $row->destino_tarja,
            'folio' => $row->folio,
            'fecha_tarja' => $row->fecha_proceso,
            'tipo_tarja' => (string) $row->tipo_tarja,
            'especie' => $row->especie,
            'envase' => (string) $row->envase,
            'embalaje' => $row->embalaje,
            'tipo_fruta' => (string) $row->tipo_fruta,
            'tipo_pallet' => (string) $row->tipo_pallet,
            'altura' => $row->altura,
            'plu' => (string) $row->plu,
            'cajas_total' => $row->cajas_total,
            'kilos_total' => $row->kilos_total,
            'lote' => $row->lote,
            'id_productor' => $row->id_productor,
            'sitio_pro' => (string) $row->sitio_pro,
            'n_variedad_proceso' => $row->n_variedad_proceso,
            'n_variedad' => $row->n_variedad,
            'n_productor_proceso' => $row->n_productor_proceso,
            'fec_embalaje' => $row->fec_embalaje,
            'n_calibre' => $row->n_calibre,
            'cajas' => $row->cajas,
            'kilos' => $row->kilos,
            't_categoria' => $row->t_categoria,
            'n_categoria' => $row->n_categoria,
        ];
    }
}
