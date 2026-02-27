<?php

namespace App\Http\Controllers;

use App\Mail\CuadraturaApprovalBatchMail;
use App\Mail\CuadraturaRejectedMail;
use App\Models\NotificationLog;
use App\Models\Proceso;
use App\Models\ProcesoCuadratura;
use App\Models\ProcesoCuadraturaEvento;
use App\Models\Recepcion;
use App\Services\Cuadratura\ProcesoCuadraturaDataService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CuadraturaController extends Controller
{
    private const STATUS_PENDING = 'pendiente_cuadratura';
    private const STATUS_SENT = 'enviado_jefe';
    private const STATUS_REJECTED = 'rechazado_jefe';
    private const STATUS_APPROVED = 'aprobado_jefe';

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->ensureModuleAccess($user);

        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));

        $query = Proceso::query()
            //->where('estado', 'Finalizado')
            ->with('cuadraturaWorkflow');

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->orWhere('n_proceso', 'like', '%' . $search . '%')
                    ->orWhere('agricola', 'like', '%' . $search . '%')
                    ->orWhere('especie', 'like', '%' . $search . '%')
                    ->orWhere('variedad', 'like', '%' . $search . '%')
                    ->orWhere('LPP_recepcion', 'like', '%' . $search . '%')
                    ->orWhere('lote_recepcion', 'like', '%' . $search . '%');
            });
        }

        $allowedStatuses = [
            self::STATUS_PENDING,
            self::STATUS_SENT,
            self::STATUS_REJECTED,
            self::STATUS_APPROVED,
        ];

        if (in_array($status, $allowedStatuses, true)) {
            if ($status === self::STATUS_PENDING) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->whereDoesntHave('cuadraturaWorkflow')
                        ->orWhereHas('cuadraturaWorkflow', function ($workflowQuery) {
                            $workflowQuery->where('estado', self::STATUS_PENDING);
                        });
                });
            } else {
                $query->whereHas('cuadraturaWorkflow', function ($workflowQuery) use ($status) {
                    $workflowQuery->where('estado', $status);
                });
            }
        }

        $procesos = $query
            ->orderByDesc('fecha')
            ->orderByDesc('n_proceso')
            ->paginate(20)
            ->withQueryString();

        $lotesByProceso = $procesos
            ->getCollection()
            ->mapWithKeys(fn (Proceso $proceso) => [$proceso->id => $this->extractProcesoLotes($proceso)]);

        $allLotes = $lotesByProceso
            ->flatten()
            ->map(fn ($lote) => trim((string) $lote))
            ->filter(fn ($lote) => $lote !== '')
            ->unique()
            ->values();

        $qualityExportableByLote = $this->buildQualityExportableByLote($allLotes);

        $procesos->getCollection()->transform(function (Proceso $proceso) use ($lotesByProceso, $qualityExportableByLote) {
            $workflow = $proceso->cuadraturaWorkflow;
            $status = $workflow?->estado ?? self::STATUS_PENDING;
            $lotes = $lotesByProceso->get($proceso->id, []);
            $exportacionPercent = $this->calculateProcessExportacionPercentage($proceso);
            $exportacionCalidadPorLote = $this->buildQualityExportablePercentagesForProcesoLotes($lotes, $qualityExportableByLote);
            $exportacionCalidadPercent = $this->calculateWeightedQualityExportablePercentage($lotes, $qualityExportableByLote);

            return [
                'id' => $proceso->id,
                'n_proceso' => $proceso->n_proceso,
                'agricola' => $proceso->agricola,
                'especie' => $proceso->especie,
                'variedad' => $proceso->variedad,
                'fecha' => $proceso->fecha,
                'lote_recepcion' => $proceso->lote_recepcion ?: $proceso->LPP_recepcion,
                'lotes' => $lotes,
                'peso_neto' => (float) ($proceso->kilos_netos ?? 0),
                'exportacion_percent' => $exportacionPercent,
                'exportacion_calidad_por_lote' => $exportacionCalidadPorLote,
                'exportacion_calidad_percent' => $exportacionCalidadPercent,
                'workflow_status' => $status,
                'workflow_status_label' => $this->statusLabel($status),
                'comentario_rechazo' => $workflow?->comentario_rechazo,
                'informe_url' => $this->resolveReportUrl($proceso),
                'preview_url' => route('cuadratura.preview', $proceso),
                'review_url' => route('cuadratura.review', $proceso),
                'enviado_jefe_at' => optional($workflow?->enviado_jefe_at)?->toDateTimeString(),
                'aprobado_jefe_at' => optional($workflow?->aprobado_jefe_at)?->toDateTimeString(),
                'rechazado_jefe_at' => optional($workflow?->rechazado_jefe_at)?->toDateTimeString(),
            ];
        });

        return Inertia::render('Cuadratura/Index', [
            'procesos' => $procesos,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => [
                ['value' => self::STATUS_PENDING, 'label' => $this->statusLabel(self::STATUS_PENDING)],
                ['value' => self::STATUS_SENT, 'label' => $this->statusLabel(self::STATUS_SENT)],
                ['value' => self::STATUS_REJECTED, 'label' => $this->statusLabel(self::STATUS_REJECTED)],
                ['value' => self::STATUS_APPROVED, 'label' => $this->statusLabel(self::STATUS_APPROVED)],
            ],
        ]);
    }

    public function preview(Request $request, Proceso $proceso): Response
    {
        $user = $request->user();
        $this->ensureReviewAccess($user);

        return Inertia::render('Cuadratura/Preview', [
            'procesoId' => $proceso->id,
            'numero' => $proceso->n_proceso,
            'htmlUrl' => route('procesos.report', ['proceso' => $proceso->id, 'preview' => 1]),
            'downloadUrl' => route('procesos.report', [
                'proceso' => $proceso->id,
                'format' => 'pdf',
                'download' => 1,
            ]),
            'reviewUrl' => route('cuadratura.review', $proceso),
        ]);
    }

    public function review(Request $request, Proceso $proceso, ProcesoCuadraturaDataService $dataService): Response
    {
        $user = $request->user();
        $this->ensureReviewAccess($user);

        $workflow = ProcesoCuadratura::query()->where('proceso_id', $proceso->id)->first();
        $workflowSnapshot = $workflow ?? new ProcesoCuadratura([
            'proceso_id' => $proceso->id,
            'estado' => self::STATUS_PENDING,
            'ciclo' => 1,
        ]);

        $eventos = $workflow
            ? $workflow->eventos()->get()->map(fn (ProcesoCuadraturaEvento $event) => [
                'id' => $event->id,
                'accion' => $event->accion,
                'detalle' => $event->detalle,
                'actor_nombre' => $event->actor_nombre,
                'actor_email' => $event->actor_email,
                'created_at' => optional($event->created_at)?->format('Y-m-d H:i:s'),
            ])->values()
            : collect();

        $numeroProceso = (string) $proceso->n_proceso;
        $cabecera = null;
        $ingresos = collect();
        $salidas = collect();
        $sqlError = null;

        try {
            $cabecera = $dataService->getCabecera($numeroProceso);
            $ingresos = $dataService->getIngresos($numeroProceso)->values();
            $salidas = $dataService->getSalidas($numeroProceso, $proceso->id_empresa)->values();
        } catch (\Throwable $e) {
            Log::error('Cuadratura SQLSRV query failed', [
                'proceso_id' => $proceso->id,
                'n_proceso' => $proceso->n_proceso,
                'error' => $e->getMessage(),
            ]);
            $sqlError = 'No fue posible consultar datos de SQLSRV para este proceso.';
        }

        return Inertia::render('Cuadratura/Review', [
            'proceso' => [
                'id' => $proceso->id,
                'n_proceso' => $proceso->n_proceso,
                'agricola' => $proceso->agricola,
                'especie' => $proceso->especie,
                'variedad' => $proceso->variedad,
                'fecha' => $proceso->fecha,
                'id_empresa' => $proceso->id_empresa,
                'lote_recepcion' => $proceso->lote_recepcion,
                'informe_url' => $this->resolveReportUrl($proceso),
            ],
            'workflow' => [
                'id' => $workflowSnapshot->id,
                'estado' => $workflowSnapshot->estado,
                'estado_label' => $this->statusLabel($workflowSnapshot->estado),
                'ciclo' => (int) ($workflowSnapshot->ciclo ?? 1),
                'comentario_rechazo' => $workflowSnapshot->comentario_rechazo,
                'enviado_jefe_at' => optional($workflowSnapshot->enviado_jefe_at)?->toDateTimeString(),
                'aprobado_jefe_at' => optional($workflowSnapshot->aprobado_jefe_at)?->toDateTimeString(),
                'rechazado_jefe_at' => optional($workflowSnapshot->rechazado_jefe_at)?->toDateTimeString(),
            ],
            'cabecera' => $cabecera,
            'ingresos' => $ingresos,
            'salidas' => $salidas,
            'sqlError' => $sqlError,
            'eventos' => $eventos,
            'canSendToChief' => $this->canManageModule($user),
            'canResolveAsChief' => $this->canResolveAsChief($user),
        ]);
    }

    public function sendForApproval(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->ensureModuleAccess($user);

        $validated = $request->validate([
            'proceso_ids' => ['required', 'array', 'min:1'],
            'proceso_ids.*' => ['integer', 'exists:procesos,id'],
        ]);

        $procesoIds = collect($validated['proceso_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $recipients = array_values(array_filter(config('cuadratura.chief_plant_emails', [])));

        if (empty($recipients)) {
            return back()->with('error', 'No hay correos configurados para Jefe de Planta (CUADRATURA_CHIEF_EMAILS).');
        }

        $procesos = Proceso::query()
            ->whereIn('id', $procesoIds)
            ->where('estado', 'Finalizado')
            ->orderBy('n_proceso')
            ->get();

        if ($procesos->isEmpty()) {
            return back()->with('error', 'No se encontraron procesos válidos para enviar.');
        }

        $actor = $this->actorMeta($user);
        $mailItems = [];
        $sentCount = 0;
        $skippedApproved = 0;

        DB::transaction(function () use ($procesos, $actor, &$mailItems, &$sentCount, &$skippedApproved) {
            foreach ($procesos as $proceso) {
                $workflow = ProcesoCuadratura::firstOrCreate(
                    ['proceso_id' => $proceso->id],
                    ['estado' => self::STATUS_PENDING, 'ciclo' => 1]
                );

                if ($workflow->estado === self::STATUS_APPROVED) {
                    $skippedApproved++;
                    continue;
                }

                $action = $workflow->estado === self::STATUS_REJECTED ? 'reenviado_jefe' : 'enviado_jefe';
                $nextCycle = $action === 'reenviado_jefe'
                    ? ((int) $workflow->ciclo + 1)
                    : max((int) $workflow->ciclo, 1);

                $workflow->fill([
                    'estado' => self::STATUS_SENT,
                    'ciclo' => $nextCycle,
                    'enviado_jefe_at' => Carbon::now('America/Santiago'),
                    'comentario_rechazo' => null,
                    'rechazado_jefe_at' => null,
                    'ultimo_actor_id' => $actor['id'],
                    'ultimo_actor_nombre' => $actor['name'],
                    'ultimo_actor_email' => $actor['email'],
                ]);
                $workflow->save();

                ProcesoCuadraturaEvento::create([
                    'proceso_cuadratura_id' => $workflow->id,
                    'proceso_id' => $proceso->id,
                    'accion' => $action,
                    'detalle' => $action === 'reenviado_jefe'
                        ? 'Proceso reenviado a Jefe de Planta tras rechazo.'
                        : 'Proceso enviado a Jefe de Planta para aprobación.',
                    'actor_user_id' => $actor['id'],
                    'actor_nombre' => $actor['name'],
                    'actor_email' => $actor['email'],
                    'meta' => ['ciclo' => $workflow->ciclo],
                ]);

                $mailItems[] = [
                    'proceso_id' => $proceso->id,
                    'n_proceso' => $proceso->n_proceso,
                    'agricola' => $proceso->agricola,
                    'especie' => $proceso->especie,
                    'variedad' => $proceso->variedad,
                    'report_url' => route('cuadratura.preview', $proceso),
                    'review_url' => route('cuadratura.review', $proceso),
                    'ver_listado_para_aprobar' => route('cuadratura.index', ['search' => '', 'status' => self::STATUS_SENT]),
                ];

                $sentCount++;
            }
        });

        if ($sentCount === 0) {
            return back()->with('error', 'Todos los procesos seleccionados ya están aprobados por Jefe de Planta.');
        }

        $to = array_shift($recipients);
        try {
            $mail = Mail::to($to);
            if (! empty($recipients)) {
                $mail->bcc($recipients);
            }
            $mail->send(new CuadraturaApprovalBatchMail($mailItems, $actor['name'] ?? 'Cuadratura'));

            NotificationLog::create([
                'type' => 'email',
                'recipient' => $to,
                'subject' => CuadraturaApprovalBatchMail::class,
                'status' => 'success',
                'context' => [
                    'channel' => 'cuadratura',
                    'action' => 'send_for_approval',
                    'proceso_ids' => array_column($mailItems, 'proceso_id'),
                    'bcc' => $recipients,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Cuadratura send for approval failed', [
                'error' => $e->getMessage(),
            ]);

            NotificationLog::create([
                'type' => 'email',
                'recipient' => $to,
                'subject' => CuadraturaApprovalBatchMail::class,
                'status' => 'failure',
                'message' => $e->getMessage(),
                'context' => [
                    'channel' => 'cuadratura',
                    'action' => 'send_for_approval',
                    'proceso_ids' => array_column($mailItems, 'proceso_id'),
                    'bcc' => $recipients,
                ],
            ]);

            return back()->with('error', 'Los procesos se marcaron como enviados, pero falló el correo al Jefe de Planta.');
        }

        $message = "Se enviaron {$sentCount} proceso(s) a aprobación.";
        if ($skippedApproved > 0) {
            $message .= " {$skippedApproved} ya estaban aprobados y se omitieron.";
        }

        return back()->with('success', $message);
    }

    public function approve(Request $request, Proceso $proceso): RedirectResponse
    {
        $user = $request->user();
        $this->ensureChiefAccess($user);

        $workflow = ProcesoCuadratura::firstOrCreate(
            ['proceso_id' => $proceso->id],
            ['estado' => self::STATUS_PENDING, 'ciclo' => 1]
        );

        if ($workflow->estado === self::STATUS_APPROVED) {
            return back()->with('success', 'El proceso ya estaba aprobado.');
        }

        $actor = $this->actorMeta($user);
        $approvalTimestamp = Carbon::now('America/Santiago');

        try {
            [$storedPath, $storedFilename] = $this->generateAndStoreApprovedProcessPdf(
                $request,
                $proceso,
                $actor,
                $approvalTimestamp
            );
        } catch (\Throwable $e) {
            Log::error('Cuadratura approve PDF generation failed', [
                'proceso_id' => $proceso->id,
                'n_proceso' => $proceso->n_proceso,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'No se pudo generar el PDF del informe. El proceso no fue aprobado.');
        }

        DB::transaction(function () use ($workflow, $proceso, $actor, $storedPath, $approvalTimestamp) {
            $workflow->fill([
                'estado' => self::STATUS_APPROVED,
                'aprobado_jefe_at' => $approvalTimestamp,
                'comentario_rechazo' => null,
                'rechazado_jefe_at' => null,
                'ultimo_actor_id' => $actor['id'],
                'ultimo_actor_nombre' => $actor['name'],
                'ultimo_actor_email' => $actor['email'],
            ]);
            $workflow->save();

            ProcesoCuadraturaEvento::create([
                'proceso_cuadratura_id' => $workflow->id,
                'proceso_id' => $proceso->id,
                'accion' => 'aprobado_jefe',
                'detalle' => 'Proceso aprobado por Jefe de Planta.',
                'actor_user_id' => $actor['id'],
                'actor_nombre' => $actor['name'],
                'actor_email' => $actor['email'],
                'meta' => ['ciclo' => $workflow->ciclo],
            ]);

            $proceso->informe = $storedPath;
            $proceso->informe_uploaded_at = $approvalTimestamp;
            $proceso->save();
        });

        return back()->with('success', "Proceso aprobado correctamente. PDF generado: {$storedFilename}");
    }

    public function reject(Request $request, Proceso $proceso): RedirectResponse
    {
        $user = $request->user();
        $this->ensureChiefAccess($user);

        $validated = $request->validate([
            'comentario' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $workflow = ProcesoCuadratura::firstOrCreate(
            ['proceso_id' => $proceso->id],
            ['estado' => self::STATUS_PENDING, 'ciclo' => 1]
        );

        if ($workflow->estado === self::STATUS_APPROVED) {
            return back()->with('error', 'No se puede rechazar un proceso que ya fue aprobado.');
        }

        $actor = $this->actorMeta($user);
        $comment = trim($validated['comentario']);

        $workflow->fill([
            'estado' => self::STATUS_REJECTED,
            'rechazado_jefe_at' => Carbon::now('America/Santiago'),
            'comentario_rechazo' => $comment,
            'ultimo_actor_id' => $actor['id'],
            'ultimo_actor_nombre' => $actor['name'],
            'ultimo_actor_email' => $actor['email'],
        ]);
        $workflow->save();

        ProcesoCuadraturaEvento::create([
            'proceso_cuadratura_id' => $workflow->id,
            'proceso_id' => $proceso->id,
            'accion' => 'rechazado_jefe',
            'detalle' => $comment,
            'actor_user_id' => $actor['id'],
            'actor_nombre' => $actor['name'],
            'actor_email' => $actor['email'],
            'meta' => ['ciclo' => $workflow->ciclo],
        ]);

        $managerEmail = trim((string) config('cuadratura.manager_email', ''));
        if ($managerEmail !== '') {
            try {
                Mail::to($managerEmail)->send(new CuadraturaRejectedMail(
                    $proceso,
                    $actor['name'] ?? 'Jefe de Planta',
                    $comment,
                    route('cuadratura.review', $proceso),
                    $this->resolveReportUrl($proceso)
                ));

                NotificationLog::create([
                    'type' => 'email',
                    'recipient' => $managerEmail,
                    'subject' => CuadraturaRejectedMail::class,
                    'status' => 'success',
                    'context' => [
                        'channel' => 'cuadratura',
                        'action' => 'reject',
                        'proceso_id' => $proceso->id,
                        'n_proceso' => $proceso->n_proceso,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::error('Cuadratura rejected email failed', [
                    'proceso_id' => $proceso->id,
                    'error' => $e->getMessage(),
                ]);

                NotificationLog::create([
                    'type' => 'email',
                    'recipient' => $managerEmail,
                    'subject' => CuadraturaRejectedMail::class,
                    'status' => 'failure',
                    'message' => $e->getMessage(),
                    'context' => [
                        'channel' => 'cuadratura',
                        'action' => 'reject',
                        'proceso_id' => $proceso->id,
                        'n_proceso' => $proceso->n_proceso,
                    ],
                ]);

                return back()->with('error', 'Proceso rechazado, pero falló el correo al encargado de cuadratura.');
            }
        }

        if ($managerEmail === '') {
            return back()->with('success', 'Proceso rechazado. Configura CUADRATURA_MANAGER_EMAIL para aviso por correo.');
        }

        return back()->with('success', 'Proceso rechazado y notificado al encargado de cuadratura.');
    }

    private function ensureModuleAccess($user): void
    {
        abort_unless($this->canManageModule($user), 403);
    }

    private function ensureReviewAccess($user): void
    {
        abort_unless($this->canManageModule($user) || $this->canResolveAsChief($user), 403);
    }

    private function ensureChiefAccess($user): void
    {
        abort_unless($this->canResolveAsChief($user), 403);
    }

    private function canManageModule($user): bool
    {
        if (! $user || ! method_exists($user, 'hasAnyRole')) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Administrador', 'Cuadratura']);
    }

    private function canResolveAsChief($user): bool
    {
        if (! $user || ! method_exists($user, 'hasAnyRole')) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Administrador', 'Jefe de Planta']);
    }

    private function actorMeta($user): array
    {
        return [
            'id' => $user?->id,
            'name' => $user?->name,
            'email' => $user?->email,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_SENT => 'Enviado a Jefe de Planta',
            self::STATUS_REJECTED => 'Rechazado por Jefe de Planta',
            self::STATUS_APPROVED => 'Aprobado por Jefe de Planta',
            default => 'Pendiente de revisión de cuadratura',
        };
    }

    private function resolveReportUrl(Proceso $proceso): ?string
    {
        return route('procesos.report', $proceso);
    }

    private function extractProcesoLotes(Proceso $proceso): array
    {
        $raw = trim((string) ($proceso->lote_recepcion ?: $proceso->LPP_recepcion ?: ''));
        if ($raw === '') {
            return [];
        }

        $tokens = collect(preg_split('/[\s,;|\/]+/', $raw) ?: [])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values();

        if ($tokens->isEmpty()) {
            return [];
        }

        $numericTokens = $tokens
            ->filter(fn ($value) => preg_match('/^\d+$/', $value) === 1)
            ->values();

        return ($numericTokens->isNotEmpty() ? $numericTokens : $tokens)
            ->unique()
            ->values()
            ->all();
    }

    private function buildQualityExportableByLote(Collection $lotes): array
    {
        if ($lotes->isEmpty()) {
            return [];
        }

        $recepciones = Recepcion::query()
            ->select(['id', 'numero_g_recepcion', 'peso_neto'])
            ->whereIn('numero_g_recepcion', $lotes->all())
            ->whereHas('calidad')
            ->with(['calidad.detalles' => function ($query) {
                $query->select('id', 'calidad_id', 'tipo_item', 'detalle_item', 'porcentaje_muestra');
            }])
            ->get();

        $byLote = [];

        foreach ($recepciones as $recepcion) {
            $lote = trim((string) ($recepcion->numero_g_recepcion ?? ''));
            if ($lote === '') {
                continue;
            }

            $byLote[$lote] = [
                'peso_neto' => (float) ($recepcion->peso_neto ?? 0),
                'porcentaje_exportable' => $this->calculateQualityExportablePercentage(
                    $recepcion->calidad?->detalles ?? collect()
                ),
            ];
        }

        return $byLote;
    }

    private function calculateProcessExportacionPercentage(Proceso $proceso): float
    {
        $totalPeso = (float) ($proceso->kilos_netos ?? 0);
        if ($totalPeso <= 0) {
            return 0;
        }

        $pesoExportacion = (float) ($proceso->exp ?? 0);
        $percentage = ($pesoExportacion / $totalPeso) * 100;

        return round(max(0, min(100, $percentage)), 2);
    }

    private function buildQualityExportablePercentagesForProcesoLotes(array $lotes, array $qualityExportableByLote): array
    {
        if (empty($lotes)) {
            return [];
        }

        $result = [];
        foreach ($lotes as $lote) {
            $snapshot = $qualityExportableByLote[(string) $lote] ?? null;
            $result[] = [
                'lote' => (string) $lote,
                'porcentaje_exportable' => $snapshot ? (float) ($snapshot['porcentaje_exportable'] ?? 0) : null,
            ];
        }

        return $result;
    }

    private function calculateWeightedQualityExportablePercentage(array $lotes, array $qualityExportableByLote): ?float
    {
        if (empty($lotes)) {
            return null;
        }

        $weightedSum = 0.0;
        $totalWeight = 0.0;
        $fallbackPercentages = [];

        foreach ($lotes as $lote) {
            $snapshot = $qualityExportableByLote[(string) $lote] ?? null;
            if (! $snapshot) {
                continue;
            }

            $percentage = (float) ($snapshot['porcentaje_exportable'] ?? 0);
            $pesoNeto = (float) ($snapshot['peso_neto'] ?? 0);

            if ($pesoNeto > 0) {
                $weightedSum += ($percentage * $pesoNeto);
                $totalWeight += $pesoNeto;
                continue;
            }

            $fallbackPercentages[] = $percentage;
        }

        if ($totalWeight > 0) {
            return round($weightedSum / $totalWeight, 2);
        }

        if ($fallbackPercentages === []) {
            return null;
        }

        return round(array_sum($fallbackPercentages) / count($fallbackPercentages), 2);
    }

    private function calculateQualityExportablePercentage(Collection $detalles): float
    {
        if ($detalles->isEmpty()) {
            return 0;
        }

        $defectosCalidad = $detalles
            ->filter(fn ($detalle) => mb_strtoupper(trim((string) ($detalle->tipo_item ?? ''))) === 'DEFECTOS DE CALIDAD')
            ->sum(fn ($detalle) => (float) ($detalle->porcentaje_muestra ?? 0));

        $defectosCondicion = $detalles
            ->filter(fn ($detalle) => mb_strtoupper(trim((string) ($detalle->tipo_item ?? ''))) === 'DEFECTOS DE CONDICIÓN')
            ->sum(fn ($detalle) => (float) ($detalle->porcentaje_muestra ?? 0));

        $danosPlaga = $detalles
            ->filter(fn ($detalle) => mb_strtoupper(trim((string) ($detalle->tipo_item ?? ''))) === 'DAÑO DE PLAGA')
            ->sum(fn ($detalle) => (float) ($detalle->porcentaje_muestra ?? 0));

        $defectosCalidadPrecalibre = $detalles
            ->filter(fn ($detalle) => mb_strtoupper(trim((string) ($detalle->tipo_item ?? ''))) === 'DEFECTOS DE CALIDAD')
            ->filter(fn ($detalle) => mb_strtoupper(trim((string) ($detalle->detalle_item ?? ''))) === 'PRECALIBRE')
            ->sum(fn ($detalle) => (float) ($detalle->porcentaje_muestra ?? 0));

        $defectosCalidadAjustado = $defectosCalidad - $defectosCalidadPrecalibre;
        $totalDefectos = $defectosCalidadAjustado + $defectosCondicion + $danosPlaga + $defectosCalidadPrecalibre;

        return round(max(0, 100 - $totalDefectos), 2);
    }

    private function generateAndStoreApprovedProcessPdf(
        Request $request,
        Proceso $proceso,
        array $actor,
        Carbon $approvalTimestamp
    ): array
    {
        $pdfRequest = Request::create(
            route('procesos.report', ['proceso' => $proceso->id]),
            'GET',
            [
                'format' => 'pdf',
                'download' => 1,
                'chief_signature' => 1,
                'chief_signature_name' => (string) ($actor['name'] ?? ''),
                'chief_signature_email' => (string) ($actor['email'] ?? ''),
                'chief_signature_at' => $approvalTimestamp->toDateTimeString(),
            ]
        );
        $pdfRequest->setUserResolver(fn () => $request->user());

        $response = app(ProcesoReportController::class)->show(
            $pdfRequest,
            $proceso->fresh(),
            app(ProcesoCuadraturaDataService::class)
        );

        if (! method_exists($response, 'getContent')) {
            throw new \RuntimeException('La respuesta de generación de informe no contiene contenido PDF.');
        }

        $pdfBinary = $response->getContent();
        if (! is_string($pdfBinary) || $pdfBinary === '' || ! str_starts_with($pdfBinary, '%PDF')) {
            throw new \RuntimeException('El contenido generado no corresponde a un PDF válido.');
        }

        $filename = $this->buildApprovedProcessPdfFilename($proceso);
        $relativePath = 'pdf-procesos/' . $filename;
        Storage::disk('public')->put($relativePath, $pdfBinary);

        return [$relativePath, $filename];
    }

    private function buildApprovedProcessPdfFilename(Proceso $proceso): string
    {
        $lpp = trim((string) ($proceso->LPP_recepcion ?? ''));
        if ($lpp === '') {
            $lpp = 'SIN_LPP';
        }

        $filename = sprintf('%s-%s-%s.pdf', $proceso->n_proceso, $proceso->id_empresa, $lpp);

        return preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'proceso.pdf';
    }
}
