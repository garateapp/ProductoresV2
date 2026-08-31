<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PreCooling\Concerns\AuthorizesPreCooling;
use App\Services\PreCooling\ReporteService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReporteController extends Controller
{
    use AuthorizesPreCooling;

    public function __construct(
        private readonly ReporteService $reporteService,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorizePreCooling($request);

        return Inertia::render('PreCooling/Reportes', [
            'estadoTuneles' => $this->reporteService->estadoTuneles(),
            'saldosCamaras' => $this->reporteService->saldosCamaras(),
        ]);
    }

    public function exportar(Request $request, string $tipo)
    {
        $this->authorizePreCooling($request);

        $filas = [];
        $nombre = 'prefrio.csv';

        if ($tipo === 'estado-tuneles') {
            $nombre = 'prefrio-estado-tuneles.csv';
            $filas = array_map(
                fn ($t) => [
                    $t['codigo'],
                    $t['nombre'],
                    $t['activo'] ? 'Sí' : 'No',
                    $t['capacidad'],
                    $t['total'],
                    $t['ingresado'],
                    $t['iniciado'],
                    $t['salido'],
                    $t['cajas'],
                ],
                $this->reporteService->estadoTuneles(),
            );
            array_unshift($filas, ['Túnel', 'Nombre', 'Activo', 'Capacidad', 'Cargas', 'Ingresadas', 'Iniciadas', 'Salidas', 'Cajas']);
        } elseif ($tipo === 'saldos') {
            $nombre = 'prefrio-saldos-camaras.csv';
            $filas = [];
            foreach ($this->reporteService->saldosCamaras() as $camara) {
                foreach ($camara['saldos'] as $s) {
                    $filas[] = [
                        $camara['codigo'],
                        $camara['nombre'],
                        $s['banda'],
                        $s['fila'],
                        $s['columna'],
                        $s['altura'],
                        $s['nivel'],
                        $s['folio'],
                        $s['tipo_proceso'],
                        $s['cajas'],
                        $s['especie'],
                        $s['variedad'],
                        $s['productor'],
                    ];
                }
            }
            array_unshift($filas, ['Cámara', 'Nombre', 'Banda', 'Fila', 'Columna', 'Altura', 'Nivel', 'Folio', 'Proceso', 'Cajas', 'Especie', 'Variedad', 'Productor']);
        } else {
            abort(404);
        }

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($filas as $fila) {
                fputcsv($out, $fila);
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
