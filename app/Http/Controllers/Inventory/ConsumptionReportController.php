<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryLocation;
use App\Models\InventoryMaterial;
use App\Models\Service;
use App\Services\Inventory\ConsumptionReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Browsershot\Browsershot;

class ConsumptionReportController extends Controller
{
    use AuthorizesInventory;

    public function __construct(private readonly ConsumptionReportService $service)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $filters = $this->readFilters($request);
        $summary = $this->service->summary($filters);

        return Inertia::render('Inventory/ConsumptionReports/Index', [
            'filters' => $filters,
            'totals' => $summary['totals'],
            'byService' => $summary['byService'],
            'byMaterial' => $summary['byMaterial'],
            'byDate' => $summary['byDate'],
            'movements' => $summary['movements'],
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
            'materials' => InventoryMaterial::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'locations' => InventoryLocation::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorizeInventory($request);

        $filters = $this->readFilters($request);
        $summary = $this->service->summary($filters);

        $filename = 'consumo-por-servicio-'.($filters['date_from'] ?: 'rango').'.csv';

        return response()->streamDownload(function () use ($summary): void {
            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Servicio', 'Material', 'Código', 'Consumo normal', 'Consumo temporal', 'Total consumo', 'Merma', 'Total']);
            foreach ($summary['byMaterial'] as $row) {
                fputcsv($output, [
                    $row['service_name'],
                    $row['material_nombre'],
                    $row['material_codigo'],
                    $row['normal'],
                    $row['temporal'],
                    $row['consumo_total'],
                    $row['merma'],
                    $row['gran_total'],
                ]);
            }
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeInventory($request);

        $filters = $this->readFilters($request);
        $summary = $this->service->summary($filters);
        $generatedAt = Carbon::now('America/Santiago')->format('d-m-Y H:i');

        $html = view('reports.consumption_by_service', [
            'filters' => $filters,
            'totals' => $summary['totals'],
            'byService' => $summary['byService'],
            'byMaterial' => $summary['byMaterial'],
            'generatedAt' => $generatedAt,
        ])->render();

        $filename = 'consumo-por-servicio-'.($filters['date_from'] ?: 'rango').'.pdf';

        try {
            $tmpDir = storage_path('app/browsershot-temp');
            if (! is_dir($tmpDir)) {
                @mkdir($tmpDir, 0755, true);
            }

            $chromePath = $this->resolveChromeBinary();
            if ($chromePath === null) {
                throw new \RuntimeException('No se encontró Chrome/Chromium. Configura CHROME_PATH o BROWSERSHOT_CHROME_PATH en .env.');
            }

            $shot = Browsershot::html($html)
                ->setTemporaryDirectory($tmpDir)
                ->setChromePath($chromePath)
                ->setOption('executablePath', $chromePath)
                ->setOption('headless', true)
                ->noSandbox()
                ->addChromiumArguments([
                    'disable-dev-shm-usage',
                    'disable-gpu',
                    'font-render-hinting=none',
                    'headless=new',
                ])
                ->waitUntilNetworkIdle()
                ->setViewport(1366, 768)
                ->showBackground()
                ->format('Letter');

            $nodePath = env('NODE_PATH') ?: env('BROWSERSHOT_NODE_PATH');
            if (! empty($nodePath) && is_file($nodePath)) {
                $shot->setNodeBinary($nodePath);
            }

            $npmPath = env('NPM_PATH') ?: env('BROWSERSHOT_NPM_PATH');
            if (! empty($npmPath) && is_file($npmPath)) {
                $shot->setNpmBinary($npmPath);
            }

            $pdf = $shot->pdf();

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (\Throwable $e) {
            abort(500, 'No se pudo generar el PDF del reporte. '.$e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readFilters(Request $request): array
    {
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        if ($dateFrom === '' && $dateTo === '') {
            $dateFrom = $dateTo = now()->toDateString();
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'service_id' => trim((string) $request->input('service_id', '')),
            'material_id' => trim((string) $request->input('material_id', '')),
            'origin_location_id' => trim((string) $request->input('origin_location_id', '')),
            'tipo_folio' => trim((string) $request->input('tipo_folio', '')),
            'incluir_mermas' => $request->has('incluir_mermas') ? $request->boolean('incluir_mermas') : true,
            'q' => trim((string) $request->input('q', '')),
        ];
    }

    private function resolveChromeBinary(): ?string
    {
        $envCandidates = [
            env('BROWSERSHOT_CHROME_PATH'),
            env('CHROME_PATH'),
            env('PUPPETEER_EXECUTABLE_PATH'),
        ];

        $windowsCandidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Chromium\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Chromium\\Application\\chrome.exe',
        ];

        $linuxCandidates = [
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/snap/bin/chromium',
            '/snap/bin/chromium-browser',
        ];

        $macCandidates = [
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
            '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
        ];

        $candidates = $envCandidates;

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = array_merge($candidates, $windowsCandidates);
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $candidates = array_merge($candidates, $linuxCandidates);
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $candidates = array_merge($candidates, $macCandidates);
        } else {
            $candidates = array_merge($candidates, $linuxCandidates, $windowsCandidates, $macCandidates);
        }

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}