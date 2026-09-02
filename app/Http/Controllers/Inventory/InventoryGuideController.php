<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response;

class InventoryGuideController extends Controller
{
    use AuthorizesInventory;

    public function pdf(Request $request): Response
    {
        $this->authorizeInventory($request);

        $generatedAt = Carbon::now('America/Santiago')->format('d-m-Y H:i');

        $html = view('reports.inventory_module_guide', [
            'generatedAt' => $generatedAt,
        ])->render();

        $filename = 'modulo-inventario-guia-de-operacion.pdf';

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
                ->showBackground()
                ->margins(0, 0, 0, 0)
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
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        } catch (\Throwable $e) {
            abort(500, 'No se pudo generar la guía en PDF. '.$e->getMessage());
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
