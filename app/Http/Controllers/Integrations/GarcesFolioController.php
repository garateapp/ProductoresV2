<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Services\Garces\GarcesProduccionFolioService;
use App\Services\Garces\PalletBox\GarcesPalletBoxService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GarcesFolioController extends Controller
{
    public function index(Request $request, GarcesProduccionFolioService $service)
    {
        $filters = [
            'q' => (string) $request->query('q', ''),
            'desde' => (string) $request->query('desde', now()->subDays(7)->toDateString()),
            'hasta' => (string) $request->query('hasta', now()->toDateString()),
            'estado' => (string) $request->query('estado', 'pendientes'),
        ];

        $folios = $service->availableFolios($filters, 25)->withQueryString();

        return Inertia::render('Integrations/Garces/Folios/Index', [
            'folios' => $folios,
            'filters' => $filters,
            'config' => [
                'enabled' => $service->isEnabled(),
                'endpoint_configured' => $service->endpoint() !== '',
                'max_folios' => GarcesProduccionFolioService::MAX_FOLIOS,
            ],
            'sourceError' => $service->sourceError,
        ]);
    }

    public function preview(Request $request, GarcesProduccionFolioService $service)
    {
        $validated = $this->validateFolios($request);

        return response()->json($service->previewFolios($validated['folios']));
    }

    public function sync(Request $request, GarcesProduccionFolioService $service)
    {
        $validated = $this->validateFolios($request);

        $result = $service->syncFolios($validated['folios'], $request->user()?->id);
        $status = $result['status'] ?? 'error';
        $ok = in_array($status, ['completed', 'partial'], true);

        return back()
            ->with($ok ? 'success' : 'error', $result['message'] ?? 'Estado: '.$status)
            ->with('garces_sync', $result);
    }

    private function validateFolios(Request $request): array
    {
        return $request->validate([
            'folios' => ['required', 'array', 'min:1', 'max:'.GarcesProduccionFolioService::MAX_FOLIOS],
            'folios.*' => ['string', 'max:100'],
        ], [], [
            'folios' => 'folios',
        ]);
    }

    public function exportExcel(Request $request, GarcesProduccionFolioService $service, GarcesPalletBoxService $palletBox)
    {
        $validated = $this->validateFolios($request);
        $folios = $validated['folios'];

        $data = $service->availableFolios(['folios' => $folios, 'estado' => ''], GarcesProduccionFolioService::MAX_FOLIOS);
        $rows = $data->getCollection()->values()->all();

        $items = $palletBox->payloadsForFolios($folios);
        $detailsByFolio = [];
        foreach ($items as $item) {
            $folio = (string) ($item['folio'] ?? '');
            if (! isset($detailsByFolio[$folio])) {
                $detailsByFolio[$folio] = [];
            }
            foreach ($item['payload']['pallets'] ?? [] as $pallet) {
                $header = $pallet['header'] ?? [];
                $detail = $pallet['detail'] ?? [];
                foreach ($detail as $d) {
                    $detailsByFolio[$folio][] = array_merge([
                        'numero_proceso' => $header['numero_proceso'] ?? $d['numeroProceso'] ?? null,
                        'fecha_proceso' => $header['fechaTarja'] ?? null,
                        'cajas_total' => (float) ($header['cajasTotal'] ?? 0),
                        'kilos_total' => round((float) ($header['kilosTotal'] ?? 0), 3),
                        'especie' => $header['especie'] ?? '',
                        'variedad_real' => $d['variedadReal'] ?? '',
                        'variedad_comercial' => $d['variedadComercial'] ?? '',
                        'categoria' => $d['categoria'] ?? '',
                        'etiqueta' => $d['etiqueta'] ?? '',
                        'calibre' => $d['calibre'] ?? '',
                        'productor_real' => $d['productorReal'] ?? '',
                        'productor' => $d['productor'] ?? '',
                        'embalaje' => $header['embalaje'] ?? '',
                        'envase' => $header['envase'] ?? '',
                    ], (array) $d);
                }
            }
        }

        $tipoTarja = function (string $folio): int {
            $upper = strtoupper($folio);
            if (str_ends_with($upper, 'T')) {
                return 2; // saldo
            }
            if (ctype_digit($folio)) {
                return 1; // folio numérico
            }
            return 1;
        };

        $tipoPallet = function (int $tipoTarja): int {
            return $tipoTarja === 2 ? 7 : 4;
        };

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Folios Garces');

        $headers = [
            'NUM_PROCESO', 'DESTINO_TARJA', 'TARJA', 'FECHA_TARJA', 'TIPO_TARJA',
            'ESPECIE', 'ENVASE', 'EMBALAJE', 'TIPO_FRUTA', 'TIPO_PALLET',
            'ALTURA', 'PLU', 'CAJAS_TOTAL', 'KILOS_TOTAL', 'LOTE',
            'PRODUCTOR_REAL', 'PRODUCTOR_COMERCIAL', 'SITIO_PRO',
            'VARIEDAD_REAL', 'VARIEDAD_COMERCIAL', 'FEC_EMBALAJE', 'CALIBRE',
            'CAJAS', 'KILOS', 'CLASIFICACION', 'CATEGORIA', 'ETIQUETA',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($rows as $r) {
            $folio = (string) ($r['folio'] ?? '');
            $tt = $tipoTarja($folio);
            $tp = $tipoPallet($tt);
            $fecha = $r['fecha_proceso'] ?? '';
            $fechaExcel = $fecha !== '' ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '';
            $fechaTexto = $fechaExcel;
            $cajasTot = (float) ($r['cajas'] ?? 0);
            $kilosTot = (float) ($r['kilos'] ?? 0);
            $folioDetails = $detailsByFolio[$folio] ?? [];

            if (empty($folioDetails)) {
                $sheet->setCellValue("A{$row}", $r['numero_proceso'] ?? '');
                $sheet->setCellValue("B{$row}", '1');
                $sheet->setCellValue("C{$row}", $folio);
                $sheet->setCellValue("D{$row}", $fechaExcel);
                $sheet->setCellValue("E{$row}", $tt);
                $sheet->setCellValue("F{$row}", $r['especie'] ?? '');
                $sheet->setCellValue("G{$row}", '');
                $sheet->setCellValue("H{$row}", '');
                $sheet->setCellValue("I{$row}", '1');
                $sheet->setCellValue("J{$row}", $tp);
                $sheet->setCellValue("K{$row}", '');
                $sheet->setCellValue("L{$row}", '1');
                $sheet->setCellValue("M{$row}", $cajasTot);
                $sheet->setCellValue("N{$row}", $kilosTot);
                $sheet->setCellValue("O{$row}", '');
                $sheet->setCellValue("P{$row}", $r['productor'] ?? '');
                $sheet->setCellValue("Q{$row}", $r['productor'] ?? '');
                $sheet->setCellValue("R{$row}", '1');
                $sheet->setCellValue("S{$row}", $r['variedad'] ?? '');
                $sheet->setCellValue("T{$row}", $r['variedad'] ?? '');
                $sheet->setCellValue("U{$row}", $fechaTexto);
                $sheet->setCellValue("V{$row}", '');
                $sheet->setCellValue("W{$row}", $cajasTot);
                $sheet->setCellValue("X{$row}", $kilosTot);
                $sheet->setCellValue("Y{$row}", '');
                $sheet->setCellValue("Z{$row}", $r['variedad_original'] ?? '');
                $sheet->setCellValue("AA{$row}", '');
                $row++;
            } else {
                foreach ($folioDetails as $d) {
                    $cajasDet = (float) ($d['cajas_total'] ?? $d['cantidad'] ?? 1);
                    $kilosDet = (float) ($d['kilos_total'] ?? $d['peso_neto'] ?? 0);
                    $sheet->setCellValue("A{$row}", $d['numero_proceso'] ?? $r['numero_proceso'] ?? '');
                    $sheet->setCellValue("B{$row}", '1');
                    $sheet->setCellValue("C{$row}", $folio);
                    $sheet->setCellValue("D{$row}", $d['fecha_proceso'] ?? $fechaExcel);
                    $sheet->setCellValue("E{$row}", $tt);
                    $sheet->setCellValue("F{$row}", $d['especie'] ?? $r['especie'] ?? '');
                    $sheet->setCellValue("G{$row}", $d['envase'] ?? '');
                    $sheet->setCellValue("H{$row}", $d['embalaje'] ?? '');
                    $sheet->setCellValue("I{$row}", '1');
                    $sheet->setCellValue("J{$row}", $tp);
                    $sheet->setCellValue("K{$row}", '');
                    $sheet->setCellValue("L{$row}", '1');
                    $sheet->setCellValue("M{$row}", $cajasDet > 0 ? $cajasDet : 1);
                    $sheet->setCellValue("N{$row}", $kilosDet > 0 ? $kilosDet : 0);
                    $sheet->setCellValue("O{$row}", '');
                    $sheet->setCellValue("P{$row}", $d['productor_real'] ?? $d['productor'] ?? $r['productor'] ?? '');
                    $sheet->setCellValue("Q{$row}", $d['productor_real'] ?? $d['productor'] ?? $r['productor'] ?? '');
                    $sheet->setCellValue("R{$row}", '1');
                    $sheet->setCellValue("S{$row}", $d['variedad_real'] ?? $d['variedad_comercial'] ?? $r['variedad'] ?? '');
                    $sheet->setCellValue("T{$row}", $d['variedad_comercial'] ?? $d['variedad_real'] ?? $r['variedad'] ?? '');
                    $sheet->setCellValue("U{$row}", $fechaTexto);
                    $sheet->setCellValue("V{$row}", $d['calibre'] ?? '');
                    $sheet->setCellValue("W{$row}", $cajasTot);
                    $sheet->setCellValue("X{$row}", $kilosTot);
                    $sheet->setCellValue("Y{$row}", '');
                    $sheet->setCellValue("Z{$row}", $d['categoria'] ?? '');
                    $sheet->setCellValue("AA{$row}", $d['etiqueta'] ?? '');
                    $row++;
                }
            }
        }

        $sheet->getStyle('A1:AA1')->getFont()->setBold(true);
        $sheet->getStyle('A1:AA1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        foreach (range('A', 'AA') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'Folios_Garces_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
