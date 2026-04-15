<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Proceso;
use App\Services\Cuadratura\ProcesoCuadraturaDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

class ProcesoReportController extends Controller
{
    public function show(Request $request, Proceso $proceso, ProcesoCuadraturaDataService $dataService)
    {
        $user = $request->user();

        $hasRolesApi = $user && method_exists($user, 'hasAnyRole');
        $isPrivileged = $hasRolesApi && $user->hasAnyRole([
            'Admin',
            'Administrador',
            'Cuadratura',
            'Jefe de Planta',
            'Calidad',
            'Gerencia',
            'Agronomo',
            'Agrónomo',
        ]);

        $canAccessAsProducer = $this->canAccessAsProducer($user, $proceso);
        $canAccessAsServiceUser = $this->canAccessAsServiceUser($user, $proceso);

        abort_unless($isPrivileged || $canAccessAsProducer || $canAccessAsServiceUser, 403);

        $cabecera = null;
        $ingresos = collect();
        $salidas = collect();
        $queryError = null;

        try {
            $numeroProceso = (string) $proceso->n_proceso;
            $cabecera = $dataService->getCabecera($numeroProceso);
            $ingresos = $dataService->getIngresos($numeroProceso);
            $salidas = $dataService->getSalidas($numeroProceso, $proceso->id_empresa);
        } catch (\Throwable $e) {
            $queryError = $e->getMessage();
        }

        $totalIngresosCantidad = (float) $ingresos->sum('cantidad');
        $totalIngresosPeso = (float) $ingresos->sum('peso');
        $totalSalidasCantidad = (float) $salidas->sum('cantidad');
        $totalSalidasPeso = (float) $salidas->sum('peso_neto');
        $ingresoPackagingNames = $ingresos
            ->map(fn ($row) => trim((string) ($row['n_embalaje'] ?? $row['c_embalaje'] ?? '')))
            ->filter()
            ->unique()
            ->values();
        $ingresoPackagingName = $ingresoPackagingNames->isEmpty()
            ? 'Ingreso a Proceso'
            : ($ingresoPackagingNames->count() === 1
                ? (string) $ingresoPackagingNames->first()
                : ((string) $ingresoPackagingNames->first()) . ' +' . ($ingresoPackagingNames->count() - 1));
        $speciesForCharts = (string) ($cabecera['n_especie'] ?? $proceso->especie ?? '');
        $chiefSignature = $this->resolveChiefSignature($request, $proceso);

        $destinoTotals = [
            'exportable' => 0.0,
            'mercado_interno' => 0.0,
            'sobrecalibre' => 0.0,
            'desecho' => 0.0,
        ];
        $destinoTotalsPeso = [
            'exportable' => 0.0,
            'mercado_interno' => 0.0,
            'sobrecalibre' => 0.0,
            'desecho' => 0.0,
        ];

        $salidasGrouped = [
            'exportable' => [],
            'mercado_interno' => [],
            'sobrecalibre' => [],
            'desecho' => [],
            'sin_clasificacion' => [],
        ];

        $calibreTotals = [];

        foreach ($salidas as $row) {
            $cantidad = (float) ($row['cantidad'] ?? 0);
            $pesoNeto = (float) ($row['peso_neto'] ?? 0);
            $categoriaCompuesta = trim(implode(' ', array_filter([
                (string) ($row['n_categoria'] ?? ''),
                (string) ($row['t_categoria'] ?? ''),
            ])));
            $destinoKey = $this->classifyDestinoCategory($categoriaCompuesta);

            if ($destinoKey !== null) {
                $destinoTotals[$destinoKey] += $cantidad;
                $destinoTotalsPeso[$destinoKey] += $pesoNeto;
                $salidasGrouped[$destinoKey][] = $row;
            } else {
                $salidasGrouped['sin_clasificacion'][] = $row;
            }

            $calibre = trim((string) ($row['n_calibre'] ?? ''));
            $calibre = $calibre !== '' ? $calibre : 'Sin calibre';
            $calibreTotals[$calibre] = ($calibreTotals[$calibre] ?? 0) + $cantidad;
        }

        uksort($calibreTotals, fn ($a, $b) => $this->compareCalibreLabels((string) $a, (string) $b));
        $calibreCurveLabels = array_values(array_keys($calibreTotals));
        $calibreCurveCantidad = array_values($calibreTotals);
        $calibreCurveTotalCantidad = array_sum($calibreCurveCantidad);
        $calibreCurvePorcentaje = array_map(
            fn ($value) => $calibreCurveTotalCantidad > 0
                ? round((((float) $value) * 100) / $calibreCurveTotalCantidad, 2)
                : 0.0,
            $calibreCurveCantidad
        );

        $totalDestinoCantidad = array_sum($destinoTotals);
        $totalDestinoPeso = array_sum($destinoTotalsPeso);

        // Regla de negocio: el 100% se calcula sobre el Peso Neto de Ingreso.
        $denominatorPeso = $totalIngresosPeso > 0 ? $totalIngresosPeso : 0.0;
        $destinoPercentages = collect($destinoTotalsPeso)
            ->map(fn ($value) => $denominatorPeso > 0 ? round(($value * 100) / $denominatorPeso, 2) : 0.0)
            ->all();

        $mermasPercentage = round(max(0, 100 - array_sum($destinoPercentages)), 2);
        $mermasPeso = max(0, $totalIngresosPeso - $totalDestinoPeso);

        $viewData = [
            'proceso' => $proceso,
            'cabecera' => $cabecera,
            'ingresos' => $ingresos,
            'salidas' => $salidas,
            'totalIngresosCantidad' => $totalIngresosCantidad,
            'totalIngresosPeso' => $totalIngresosPeso,
            'totalSalidasCantidad' => $totalSalidasCantidad,
            'totalSalidasPeso' => $totalSalidasPeso,
            'ingresoPackagingName' => $ingresoPackagingName,
            'diferenciaPeso' => $totalIngresosPeso - $totalSalidasPeso,
            'queryError' => $queryError,
            'generatedAt' => Carbon::now('America/Santiago')->format('d-m-Y H:i'),
            'speciesForCharts' => $speciesForCharts,
            'destinoTotals' => $destinoTotals,
            'destinoTotalsPeso' => $destinoTotalsPeso,
            'destinoPercentages' => $destinoPercentages,
            'totalDestinoCantidad' => $totalDestinoCantidad,
            'totalDestinoPeso' => $totalDestinoPeso,
            'mermasPercentage' => $mermasPercentage,
            'mermasPeso' => $mermasPeso,
            'calibreCurveLabels' => $calibreCurveLabels,
            'calibreCurveCantidad' => $calibreCurveCantidad,
            'calibreCurvePorcentaje' => $calibreCurvePorcentaje,
            'salidasExportacion' => collect($salidasGrouped['exportable']),
            'salidasMercadoInterno' => collect($salidasGrouped['mercado_interno']),
            'salidasSobrecalibre' => collect($salidasGrouped['sobrecalibre']),
            'salidasDesecho' => collect($salidasGrouped['desecho']),
            'salidasSinClasificacion' => collect($salidasGrouped['sin_clasificacion']),
            'chiefSignature' => $chiefSignature,
        ];

        if ($request->query('format') === 'pdf') {
            return $this->renderPdf($request, $viewData);
        }

        return view('reports.process_report', $viewData);
    }

    private function renderPdf(Request $request, array $viewData)
    {
        $proceso = $viewData['proceso'];

        $html = view('reports.process_report', $viewData)->render();
        $download = $request->boolean('download', true);
        $filename = $proceso->n_proceso.'-'.$proceso->id_empresa.'-'.$proceso->LPP_recepcion.'.pdf';

        try {
            $tmpDir = storage_path('app/browsershot-temp');
            if (! is_dir($tmpDir)) {
                @mkdir($tmpDir, 0755, true);
            }

            $chromePath = $this->resolveChromeBinary();
            if ($chromePath === null) {
                throw new \RuntimeException(
                    'No se encontró Chrome/Chromium. Configura CHROME_PATH o BROWSERSHOT_CHROME_PATH en .env.'
                );
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
                ->wait(1)
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
                'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            Log::error('Process report PDF generation failed', [
                'proceso_id' => $proceso->id ?? null,
                'n_proceso' => $proceso->n_proceso ?? null,
                'os' => PHP_OS_FAMILY,
                'chrome_path' => $this->resolveChromeBinary(),
                'error' => $e->getMessage(),
            ]);

            abort(500, 'No se pudo generar el PDF del informe de proceso.');
        }
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
            '/home/forge/.cache/puppeteer/chrome/linux-139.0.7258.138/chrome-linux64/chrome',
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

    private function classifyDestinoCategory(?string $categoria): ?string
    {
        $normalized = $this->normalizeText($categoria);
        $compact = str_replace(' ', '', $normalized);

        if ($normalized === '') {
            return null;
        }

        if (str_contains($compact, 'sobrecalibre')) {
            return 'sobrecalibre';
        }

        if (
            str_contains($compact, 'comercial')
            || str_contains($compact, 'precalibre')
        ) {
            return 'mercado_interno';
        }

        if (str_contains($compact, 'desecho')) {
            return 'desecho';
        }

        if (str_contains($compact, 'supermercado')) {
            return 'exportable';
        }
         if (str_contains($compact, 'vega')) {
            return 'exportable';
        }
        if (preg_match('/\bcat(?:egoria)?\s*(?:1|i)\b/u', $normalized) === 1) {
            return 'exportable';
        }

        return null;
    }

    private function normalizeText(?string $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = mb_strtolower(trim($value));

        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);

        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim($value);
    }

    private function compareCalibreLabels(string $a, string $b): int
    {
        $rankMap = [
            'L' => 1,
            'XL' => 2,
            'J' => 3,
            '2J' => 4,
            '3J' => 5,
            '4J' => 6,
            '5J' => 7,
            '6J' => 8,
            '7J' => 9,
        ];

        $normalizedA = mb_strtoupper(trim($a));
        $normalizedB = mb_strtoupper(trim($b));

        $hasRankA = array_key_exists($normalizedA, $rankMap);
        $hasRankB = array_key_exists($normalizedB, $rankMap);

        if ($hasRankA && $hasRankB) {
            return $rankMap[$normalizedA] <=> $rankMap[$normalizedB];
        }

        if ($hasRankA) {
            return -1;
        }

        if ($hasRankB) {
            return 1;
        }

        $numericA = $this->extractNumericValue($normalizedA);
        $numericB = $this->extractNumericValue($normalizedB);

        if ($numericA !== null && $numericB !== null) {
            return $numericA <=> $numericB;
        }

        if ($numericA !== null) {
            return -1;
        }

        if ($numericB !== null) {
            return 1;
        }

        return strnatcmp($normalizedA, $normalizedB);
    }

    private function extractNumericValue(string $value): ?float
    {
        if (preg_match('/-?\d+(?:[.,]\d+)?/', $value, $match) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $match[0]);
    }

    private function resolveChiefSignature(Request $request, Proceso $proceso): array
    {
        $proceso->loadMissing('cuadraturaWorkflow');
        $workflow = $proceso->cuadraturaWorkflow;

        $workflowApproved = (string) ($workflow?->estado ?? '') === 'aprobado_jefe';
        $forceSignature = $request->boolean('chief_signature');
        $enabled = $workflowApproved || $forceSignature;

        if (! $enabled) {
            return [
                'enabled' => false,
                'name' => null,
                'email' => null,
                'signed_at' => null,
            ];
        }

        $name = trim((string) $request->query('chief_signature_name', ''));
        if ($name === '') {
            $name = trim((string) ($workflow?->ultimo_actor_nombre ?? ''));
        }
        if ($name === '') {
            $name = 'Jefe de Planta';
        }

        $email = trim((string) $request->query('chief_signature_email', ''));
        if ($email === '') {
            $email = trim((string) ($workflow?->ultimo_actor_email ?? ''));
        }

        $signedAt = null;
        $signedAtRaw = trim((string) $request->query('chief_signature_at', ''));
        if ($signedAtRaw !== '') {
            try {
                $signedAt = Carbon::parse($signedAtRaw)->timezone('America/Santiago')->format('d-m-Y H:i');
            } catch (\Throwable) {
                $signedAt = null;
            }
        }

        if ($signedAt === null && $workflow?->aprobado_jefe_at) {
            $signedAt = $workflow->aprobado_jefe_at->copy()->timezone('America/Santiago')->format('d-m-Y H:i');
        }

        return [
            'enabled' => true,
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'signed_at' => $signedAt,
        ];
    }

    private function canAccessAsProducer($user, Proceso $proceso): bool
    {
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('Productor')) {
            return false;
        }

        $allowedNames = collect([
            $user->name,
        ])->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => mb_strtolower(trim($value)))
            ->values();

        $allowedCodes = collect([
            $user->csg,
            $this->normalizeProducerCode($user->csg),
            $user->idprod,
            $this->normalizeProducerCode($user->idprod),
        ])->filter()->map(fn ($value) => (string) $value)->unique()->values();

        $processCodes = collect([
            $proceso->c_productor,
            $this->normalizeProducerCode($proceso->c_productor),
        ])->filter()->map(fn ($value) => (string) $value)->unique()->values();

        if ($processCodes->isNotEmpty() && $allowedCodes->intersect($processCodes)->isNotEmpty()) {
            return true;
        }

        $processNames = collect([
            $proceso->agricola,
            $proceso->LPP_recepcion,
        ])->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => mb_strtolower(trim($value)))
            ->values();

        return $processNames->isNotEmpty() && $allowedNames->intersect($processNames)->isNotEmpty();
    }

    private function canAccessAsServiceUser($user, Proceso $proceso): bool
    {
        if (! $user || ! empty($user->idprod) || ! method_exists($user, 'services')) {
            return false;
        }

        $ownedServiceProducers = Service::where('owner_id', $user->id)
            ->with(['users:id,idprod,name,csg'])
            ->get()
            ->pluck('users')
            ->flatten();

        $memberServiceProducers = $user->services()
            ->with(['users:id,idprod,name,csg'])
            ->get()
            ->pluck('users')
            ->flatten();

        $allServiceProducers = $ownedServiceProducers->merge($memberServiceProducers);

        if ($allServiceProducers->isEmpty()) {
            return false;
        }

        $allowedNames = $allServiceProducers
            ->pluck('name')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => mb_strtolower(trim($value)))
            ->unique()
            ->values();

        $allowedCodes = $allServiceProducers
            ->flatMap(function ($producer) {
                return collect([
                    $producer->csg,
                    $this->normalizeProducerCode($producer->csg),
                    $producer->idprod,
                    $this->normalizeProducerCode($producer->idprod),
                ]);
            })
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values();

        $processCodes = collect([
            $proceso->c_productor,
            $this->normalizeProducerCode($proceso->c_productor),
        ])->filter()->map(fn ($value) => (string) $value)->unique()->values();

        if ($processCodes->isNotEmpty() && $allowedCodes->intersect($processCodes)->isNotEmpty()) {
            return true;
        }

        $processNames = collect([
            $proceso->agricola,
            $proceso->LPP_recepcion,
        ])->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => mb_strtolower(trim($value)))
            ->values();

        return $processNames->isNotEmpty() && $allowedNames->intersect($processNames)->isNotEmpty();
    }

    private function normalizeProducerCode($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }
}
