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
                return 2;
            }
            if (ctype_digit($folio)) {
                return 1;
            }
            return 1;
        };

        $spreadsheet = new Spreadsheet();

        // Hoja 1: Consolidado
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Consolidado');

        $headers1 = [
            'FOLIO', 'FECHA_PROCESO', 'N_PROCESO', 'PRODUCTOR', 'VARIEDAD', 'ESPECIE',
            'CALIBRE', 'CATEGORIA', 'EMBALAJE', 'ENVASSE', 'CAJAS_TOTAL', 'KILOS_TOTAL', 'PALLETS', 'CAJAS_DETALLE',
        ];
        $sheet1->fromArray($headers1, null, 'A1');

        $row1 = 2;
        foreach ($rows as $r) {
            $folio = (string) ($r['folio'] ?? '');
            $folioDetails = $detailsByFolio[$folio] ?? [];
            $first = $folioDetails[0] ?? [];
            $pallets = count(array_unique(array_column($folioDetails, 'numero_proceso')));
            $cajasDetalle = count($folioDetails);
            $cajasTot = (float) ($first['cajas_total'] ?? ($r['cajas'] ?? 0));
            $kilosTot = (float) ($first['kilos_total'] ?? ($r['kilos'] ?? 0));

            $sheet1->setCellValue("A{$row1}", $folio);
            $sheet1->setCellValue("B{$row1}", $r['fecha_proceso'] ?? '');
            $sheet1->setCellValue("C{$row1}", $r['numero_proceso'] ?? $first['numero_proceso'] ?? '');
            $sheet1->setCellValue("D{$row1}", $r['productor'] ?? $first['productor'] ?? '');
            $sheet1->setCellValue("E{$row1}", $r['variedad'] ?? $first['variedad_real'] ?? $first['variedad_comercial'] ?? '');
            $sheet1->setCellValue("F{$row1}", $r['especie'] ?? $first['especie'] ?? '');
            $sheet1->setCellValue("G{$row1}", $first['calibre'] ?? '');
            $sheet1->setCellValue("H{$row1}", $first['categoria'] ?? '');
            $sheet1->setCellValue("I{$row1}", $first['embalaje'] ?? '');
            $sheet1->setCellValue("J{$row1}", $first['envase'] ?? '');
            $sheet1->setCellValue("K{$row1}", $cajasTot);
            $sheet1->setCellValue("L{$row1}", $kilosTot);
            $sheet1->setCellValue("M{$row1}", $pallets);
            $sheet1->setCellValue("N{$row1}", $cajasDetalle);
            $row1++;
        }

        $endRow1 = $row1 - 1;
        $sheet1->getStyle("A1:N{$endRow1}")->getFont()->setBold(true);
        $sheet1->getStyle("A1:N1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        foreach (range('A', 'N') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        // Hoja 2: Detalle
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Detalle');

        $headers2 = [
            'FOLIO', 'N_PROCESO', 'FECHA_PROCESO', 'CAJA_ID', 'KILOS', 'LOTE', 'PRODUCTOR_REAL',
            'PRODUCTOR', 'VARIEDAD_REAL', 'VARIEDAD_COMERCIAL', 'CALIBRE', 'CATEGORIA', 'ETIQUETA',
            'FECHA_EMBALAJE', 'CLASIFICACION', 'CAJAS_TOTAL', 'KILOS_TOTAL', 'EMBALAJE', 'ENVASSE',
        ];
        $sheet2->fromArray($headers2, null, 'A1');

        $row2 = 2;
        foreach ($detailsByFolio as $folio => $folioDetails) {
            foreach ($folioDetails as $d) {
                $sheet2->setCellValue("A{$row2}", $folio);
                $sheet2->setCellValue("B{$row2}", $d['numero_proceso'] ?? '');
                $sheet2->setCellValue("C{$row2}", $d['fecha_proceso'] ?? '');
                $sheet2->setCellValue("D{$row2}", $d['idCaja'] ?? $d['ncaja'] ?? $d['NumeroCaja'] ?? '');
                $sheet2->setCellValue("E{$row2}", (float) ($d['kilos'] ?? $d['Peso_medido'] ?? $d['peso_medido'] ?? $d['PesoMedido'] ?? 0));
                $sheet2->setCellValue("F{$row2}", $d['lote'] ?? '');
                $sheet2->setCellValue("G{$row2}", $d['productor_real'] ?? '');
                $sheet2->setCellValue("H{$row2}", $d['productor'] ?? '');
                $sheet2->setCellValue("I{$row2}", $d['variedad_real'] ?? '');
                $sheet2->setCellValue("J{$row2}", $d['variedad_comercial'] ?? '');
                $sheet2->setCellValue("K{$row2}", $d['calibre'] ?? '');
                $sheet2->setCellValue("L{$row2}", $d['categoria'] ?? '');
                $sheet2->setCellValue("M{$row2}", $d['etiqueta'] ?? '');
                $sheet2->setCellValue("N{$row2}", $d['fechaEmbalaje'] ?? '');
                $sheet2->setCellValue("O{$row2}", $d['clasificacion'] ?? $d['nota_calidad'] ?? '');
                $sheet2->setCellValue("P{$row2}", (float) ($d['cajas_total'] ?? 0));
                $sheet2->setCellValue("Q{$row2}", (float) ($d['kilos_total'] ?? 0));
                $sheet2->setCellValue("R{$row2}", $d['embalaje'] ?? '');
                $sheet2->setCellValue("S{$row2}", $d['envase'] ?? '');
                $row2++;
            }
        }

        if ($row2 === 2) {
            $sheet2->setCellValue("A2", 'Sin detalle de cajas.');
        }

        $endRow2 = $row2 - 1;
        $sheet2->getStyle("A1:S{$endRow2}")->getFont()->setBold(true);
        $sheet2->getStyle("A1:S1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        foreach (range('A', 'S') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
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
