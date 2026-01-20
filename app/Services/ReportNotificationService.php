<?php

namespace App\Services;

use App\Mail\ProcessReportUploaded;
use App\Mail\ReceptionReportApproved;
use App\Models\Proceso;
use App\Models\Recepcion;
use App\Models\User;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ReportNotificationService
{
    public function notifyProcessReport(Proceso $proceso, string $storedPath, string $originalFilename): void
    {
        Log::debug('notifyProcessReport: incoming proceso', [
            'proceso_id' => $proceso->id,
            'n_proceso' => $proceso->n_proceso,
        ]);

        if (empty($proceso->agricola) && empty($proceso->LLP_recepcion) && empty($proceso->c_productor)) {
            Log::warning('Report notification skipped: process without c_productor', [
                'proceso_id' => $proceso->id,
                'n_proceso' => $proceso->n_proceso,
            ]);

            return;
        }

        $producer = $this->resolveProcessProducer($proceso);

        if (! $producer) {
            Log::warning('Report notification skipped: producer not found for c_productor', [
                'c_productor' => $proceso->c_productor,
                'proceso_id' => $proceso->id,
                'n_proceso' => $proceso->n_proceso,
            ]);

            return;
        }



        $reportUrl = $this->resolvePublicUrlFromDisk($storedPath);
        $reportDiskPath = $this->resolveAbsolutePathFromDisk($storedPath);
        $safeFilename = $this->sanitizeFilename($originalFilename ?: basename($storedPath));
        $formattedDate = $this->formatDate($proceso->fecha);

        $context = [
            'channel' => 'process',
            'producer_id' => $producer->id,
            'producer_name' => $producer->name,
            'c_productor' => $proceso->c_productor,
            'proceso_id' => $proceso->id,
            'n_proceso' => $proceso->n_proceso,
            'report_path' => $storedPath,
        ];

        $phones = $this->gatherPhones($producer);
        $emailRecipient = $this->gatherEmail($producer);
        [$phones, $emailRecipient] = $this->applyLocalOverrides($phones, $emailRecipient, (bool) $producer->emnotification, $context);

        if ($phones->isNotEmpty()) {
            Log::info('Report notification: WhatsApp recipients resolved', $context + [
                'phones' => $phones->values()->all(),
                'phone_count' => $phones->count(),
            ]);
        } else {
            Log::info('Report notification: no WhatsApp recipients resolved', $context);
        }

        if ($emailRecipient) {
            Log::info('Report notification: email recipient resolved', $context + [
                'email' => $emailRecipient,
            ]);
        }
        $this->sendEmailToDistributionList(
            new ProcessReportUploaded(
                $producer,
                $proceso,
                $reportUrl,
                $reportDiskPath,
                $safeFilename,
                $formattedDate
            ),
            $context
        );
        if ($producer->emnotification) {
            if ($emailRecipient) {
                $this->sendEmail(
                    $emailRecipient,
                    new ProcessReportUploaded(
                        $producer,
                        $proceso,
                        $reportUrl,
                        $reportDiskPath,
                        $safeFilename,
                        $formattedDate
                    ),
                    $context
                );
                sleep(2);
            } else {
                Log::info('Report notification: email enabled but no recipient resolved', $context);
            }
        }
        if ($phones->isEmpty()) {
            Log::info('Report notification: no WhatsApp numbers found', $context);
        } else {
            $message = $this->buildProcessWhatsappBody($producer->name, $proceso->n_proceso, $formattedDate, $reportUrl);

            $this->sendWhatsappNotifications(
                $phones,
                [
                    'template' => config('process_notifications.whatsapp.templates.process', 'proceso'),
                    'document_link' => $reportUrl,
                    'filename' => $safeFilename,
                    'body' => $message,
                ],
                $context
            );
        }

    }

    public function notifyReceptionReport(Recepcion $recepcion, string $publicUrl, ?string $absolutePath = null, ?string $originalFilename = null): void
    {

        if (empty($recepcion->id_emisor)) {
            Log::warning('Reception notification skipped: missing id_emisor', [
                'recepcion_id' => $recepcion->id,
                'numero_g_recepcion' => $recepcion->numero_g_recepcion,
            ]);

            return;
        }
        if($recepcion->n_emisor!=$recepcion->n_productor_rotulado){


            $producer = $this->resolveProducerByIdprod($recepcion->id_productor_rotulado);

        }
        else{
            $producer = $this->resolveProducerByIdprod($recepcion->id_emisor);

        }


        if (! $producer) {
            Log::warning('Reception notification skipped: producer not found for id_emisor', [
                'id_emisor' => $recepcion->id_emisor,
                'recepcion_id' => $recepcion->id,
                'numero_g_recepcion' => $recepcion->numero_g_recepcion,
            ]);

            return;
        }

        $safeFilename = $this->sanitizeFilename(
            $originalFilename ?: ('reporte_recepcion_' . $recepcion->numero_g_recepcion . '.pdf')
        );
        $formattedDate = $this->formatDate($recepcion->fecha_g_recepcion);
        $reportDiskPath = $this->validateAbsolutePath($absolutePath);

        $context = [
            'channel' => 'recepcion',
            'producer_id' => $producer->id,
            'producer_name' => $producer->name,
            'id_emisor' => $recepcion->id_emisor,
            'recepcion_id' => $recepcion->id,
            'numero_g_recepcion' => $recepcion->numero_g_recepcion,
            'report_url' => $publicUrl,
            'usuario_solicita' => auth()->user()->name ?? null,
        ];
        Log::info('Reception notification: sending report', $context);
        $phones = $this->gatherPhones($producer);
        $emailRecipient = $this->gatherEmail($producer);
        [$phones, $emailRecipient] = $this->applyLocalOverrides($phones, $emailRecipient, (bool) $producer->emnotification, $context);

        if ($phones->isNotEmpty()) {
            Log::info('Reception notification: WhatsApp recipients resolved', $context + [
                'phones' => $phones->values()->all(),
                'phone_count' => $phones->count(),
            ]);
        } else {
            Log::info('Reception notification: no WhatsApp recipients resolved', $context);
        }

        if ($emailRecipient) {
            Log::info('Reception notification: email recipient resolved', $context + [
                'email' => $emailRecipient,
            ]);
        }

        if ($phones->isEmpty()) {
            Log::info('Reception notification: no WhatsApp numbers found', $context);
        } else {
            $message = $this->buildReceptionWhatsappBody($producer->name, $recepcion->numero_g_recepcion, $formattedDate, $publicUrl);
            $documentLink = $this->buildDocumentLink($publicUrl);
            $bodyParams = array_values(array_filter([
                $recepcion->numero_g_recepcion
                    ? 'Recepción #' . $recepcion->numero_g_recepcion
                    : 'Informe de recepción',
                $publicUrl,
            ]));
            $this->sendWhatsappNotifications(
                $phones,
                [
                    'template' => config('process_notifications.whatsapp.templates.reception', 'recepcion'),
                    'document_link' => $documentLink,
                    'filename' => $safeFilename,
                    'body' => $message,
                    'body_params' => $bodyParams,
                ],
                $context
            );
        }
        $this->sendEmailToDistributionList(
            new ReceptionReportApproved(
                $producer,
                $recepcion,
                $publicUrl,
                $reportDiskPath,
                $safeFilename,
                $formattedDate
            ),
            $context
        );
        if ($producer->emnotification && $emailRecipient) {
            Log::info('Reception notification: producer email enabled, sending to fallback', $context);
            $this->sendEmail(
                $emailRecipient,
                new ReceptionReportApproved(
                    $producer,
                    $recepcion,
                    $publicUrl,
                    $reportDiskPath,
                    $safeFilename,
                    $formattedDate
                ),
                $context
            );
        } else {
            Log::info('Reception notification: producer email disabled or missing, sending to fallback', $context);
            $this->sendEmail(
                'carlos.alvarez@greenex.cl',
                new ReceptionReportApproved(
                    $producer,
                    $recepcion,
                    $publicUrl,
                    $reportDiskPath,
                    $safeFilename,
                    $formattedDate
                ),
                $context
            );
            sleep(2);
        }

    }

    private function resolveProducerByCsg(?string $csg): ?User
    {
        if (empty($csg)) {
            return null;
        }
        Log::info('Report notification: resolving producer by CSG', ['csg' => $csg]);

        return User::where('csg', $csg)->first();
    }

    private function resolveProducerByIdprod(?string $idprod): ?User
    {
        Log::info("iddp:".$idprod);
        if (empty($idprod)) {
            return null;
        }

        return User::where('idprod', $idprod)->first();
    }

    private function resolveProducerByName(?string $nombre): ?User
    {
        $nombre = is_string($nombre) ? trim($nombre) : '';
        if ($nombre === '') {
            return null;
        }

        return User::whereRaw('LOWER(name) = ?', [mb_strtolower($nombre)])->first();
    }

    private function resolveProcessProducer(Proceso $proceso): ?User
    {
        $attemptLog = [
            'proceso_id' => $proceso->id,
            'n_proceso' => $proceso->n_proceso,
            'c_productor' => $proceso->c_productor,
        ];

        $nameCandidates = collect([
            $proceso->LLP_recepcion,
            $proceso->agricola,
        ])->filter(fn ($value) => is_string($value) && trim($value) !== '');

        foreach ($nameCandidates as $name) {
            $producer = $this->resolveProducerByName($name);
            if ($producer) {
                Log::info('Report notification: producer resolved by name', $attemptLog + ['matched_name' => $name, 'producer_id' => $producer->id]);

                return $producer;
            }
        }

        if (! empty($proceso->c_productor)) {
            $producer = $this->resolveProducerByCsg($proceso->c_productor);
            if ($producer) {
                Log::info('Report notification: producer resolved by CSG', $attemptLog + ['producer_id' => $producer->id]);

                return $producer;
            }
        }

        return null;
    }

    private function resolvePublicUrlFromDisk(string $storedPath): ?string
    {
        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($storedPath)) {
                Log::warning('Report notification: file not found when resolving URL', ['path' => $storedPath]);

                return null;
            }

            return $disk->url($storedPath);
        } catch (\Throwable $e) {
            Log::error('Report notification: error resolving public URL', [
                'path' => $storedPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function sendReceptionWhatsappToPhone(Recepcion $recepcion, string $publicUrl, string $phone): void
    {
        $formattedDate = $this->formatDate($recepcion->fecha_g_recepcion);
        $producerName = $recepcion->n_productor_rotulado ?: $recepcion->n_emisor ?: 'Productor';
        $context = [
            'channel' => 'recepcion',
            'recepcion_id' => $recepcion->id,
            'numero_g_recepcion' => $recepcion->numero_g_recepcion,
            'manual_phone' => $phone,
            'report_url' => $publicUrl,
            'usuario_solicita' => auth()->user()->name ?? null,
        ];

        $message = $this->buildReceptionWhatsappBody($producerName, $recepcion->numero_g_recepcion, $formattedDate, $publicUrl);
        $documentLink = $this->buildDocumentLink($publicUrl);
        $bodyParams = array_values(array_filter([
            $recepcion->numero_g_recepcion
                ? 'Recepcion #' . $recepcion->numero_g_recepcion
                : 'Informe de recepcion',
            $publicUrl,
        ]));

        $this->sendWhatsappNotifications(
            collect([$phone]),
            [
                'template' => config('process_notifications.whatsapp.templates.reception', 'recepcion'),
                'document_link' => $documentLink,
                'filename' => $this->sanitizeFilename('reporte_recepcion_' . $recepcion->numero_g_recepcion . '.pdf'),
                'body' => $message,
                'body_params' => $bodyParams,
            ],
            $context
        );
    }

    private function resolveAbsolutePathFromDisk(string $storedPath): ?string
    {
        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($storedPath)) {
                return null;
            }

            return $disk->path($storedPath);
        } catch (\Throwable $e) {
            Log::error('Report notification: error resolving absolute path', [
                'path' => $storedPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function validateAbsolutePath(?string $absolutePath): ?string
    {
        if (! $absolutePath) {
            return null;
        }

        if (! is_string($absolutePath) || $absolutePath === '') {
            return null;
        }

        if (! is_file($absolutePath)) {
            Log::info('Report notification: attachment file not found', ['path' => $absolutePath]);

            return null;
        }

        return $absolutePath;
    }

    private function buildDocumentLink(string $publicUrl): string
    {
        if (filter_var($publicUrl, FILTER_VALIDATE_URL)) {
            return $publicUrl;
        }

        $trimmed = ltrim($publicUrl, '/');

        if (str_starts_with($trimmed, 'storage/')) {
            return asset($trimmed);
        }

        return asset('storage/' . $trimmed);
    }

    private function sanitizeFilename(string $filename): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

        return $clean ?: 'documento.pdf';
    }

    private function formatDate(?string $rawDate): ?string
    {
        if (empty($rawDate)) {
            return null;
        }

        try {
            return Carbon::parse($rawDate)->format('d-m-Y');
        } catch (\Throwable $e) {
            Log::warning('Report notification: unable to format date', [
                'raw_date' => $rawDate,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function gatherPhones(User $producer): Collection
    {
        return $producer->telefonos()
            ->pluck('numero')
            ->filter()
            ->map(fn (string $numero) => trim($numero))
            ->filter()
            ->unique()
            ->values();
    }

    private function gatherEmail(User $producer): ?string
    {
        if (! $producer->emnotification) {
            return null;
        }

        $email = trim((string) $producer->email);

        return $email !== '' ? $email : null;
    }

    /**
     * Apply local-environment overrides so WhatsApp/email go to test recipients.
     *
     * @return array{0: Collection, 1: ?string}
     */
    private function applyLocalOverrides(Collection $phones, ?string $emailRecipient, bool $emailEnabled, array $context): array
    {
        if (! app()->environment('local')) {
            return [$phones, $emailRecipient];
        }

        $testPhone = config('process_notifications.local_test.phone');
        if ($testPhone) {
            Log::info('Local report notification: overriding WhatsApp recipients', $context + ['test_phone' => $testPhone]);
            $phones = collect([$testPhone]);
        } else {
            Log::info('Local report notification: skipping WhatsApp send (no test phone configured)', $context);
            $phones = collect();
        }

        if ($emailEnabled) {
            $testEmail = config('process_notifications.local_test.email');
            if ($testEmail) {
                Log::info('Local report notification: overriding email recipient', $context + ['test_email' => $testEmail]);
                $emailRecipient = $testEmail;
            } else {
                Log::info('Local report notification: skipping email send (no test email configured)', $context);
                $emailRecipient = null;
            }
        } else {
            $emailRecipient = null;
        }

        return [$phones, $emailRecipient];
    }

    private function sendWhatsappNotifications(Collection $phones, array $payload, array $context): void
    {
        if ($phones->isEmpty()) {
            return;
        }

        $template = $payload['template'] ?? null;
        $documentLink = $payload['document_link'] ?? null;
        $filename = $payload['filename'] ?? 'documento.pdf';
        $body = $payload['body'] ?? '';
        $bodyParams = $payload['body_params'] ?? null;

        foreach ($phones as $phone) {
            $normalized = $this->normalizePhone($phone);

            if (! $normalized) {
                Log::warning('Report notification: unable to normalize WhatsApp number', $context + [
                    'phone_original' => $phone,
                ]);
                continue;
            }

            $phoneContext = $context + [
                'phone_original' => $phone,
                'phone_normalized' => $normalized,
            ];
            Log::info('Report notification: sending WhatsApp message', $phoneContext + [
                'has_document' => (bool) $documentLink,
                'template' => $template,
            ]);
            Log::info('DocumentLink: ' . $documentLink, $phoneContext);
            if ($documentLink) {
                $this->sendWhatsappTemplateMessage(
                    $normalized,
                    $template,
                    $documentLink,
                    $filename,
                    $body,
                    $phoneContext,
                    is_array($bodyParams) ? $bodyParams : null
                );
            } else {
                $this->sendWhatsappTextMessage($normalized, $body, $phoneContext);
            }
             sleep(3);
        }
    }

    private function sendWhatsappTemplateMessage(
        string $phone,
        ?string $templateName,
        string $documentLink,
        string $filename,
        string $body,
        array $context,
        ?array $bodyParams = null
    ): void {
        $token = config('process_notifications.whatsapp.token');
        $phoneId = config('process_notifications.whatsapp.phone_id');
        $apiVersion = config('process_notifications.whatsapp.api_version', 'v16.0');
        $templateToUse = $templateName ?: config('process_notifications.whatsapp.templates.process', 'proceso');
        Log::info('Template: ' . $templateToUse, $context + [
            'document_link' => $documentLink,
            'filename' => $filename,
        ]);
        if (empty($token) || empty($phoneId)) {
            Log::warning('Report notification: WhatsApp credentials missing', $context);

            return;
        }

        try {
            $components = [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'link' => $documentLink,
                                'filename' => $filename,
                            ],
                        ],
                    ],
                ],
            ];

            $bodyParameters = [];
            if (is_array($bodyParams) && ! empty($bodyParams)) {
                foreach ($bodyParams as $param) {
                    if ($param === null) {
                        continue;
                    }

                    $bodyParameters[] = [
                        'type' => 'text',
                        'text' => (string) $param,
                    ];
                }
            } elseif ($body !== '') {
                $bodyParameters[] = [
                    'type' => 'text',
                    'text' => $body,
                ];
            }

            if (! empty($bodyParameters)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => $bodyParameters,
                ];
            }

            $response = Http::withToken($token)
                ->acceptJson()
                ->post("https://graph.facebook.com/{$apiVersion}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateToUse,
                        'language' => [
                            'code' => 'es',
                        ],
                        'components' => $components,
                    ],
                ]);
                Log::info('Respuesta WhatsApp: ' . $response);
            if (! $response->successful()) {
                Log::error('Report notification: WhatsApp template send failed', $context + [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                NotificationLog::create([
                    'type' => 'whatsapp',
                    'recipient' => $phone,
                    'status' => 'failure',
                    'message' => 'Template send failed: ' . $response->body(),
                    'context' => $context,
                ]);
            } else {
                Log::info('Report notification: WhatsApp template sent', $context);

                NotificationLog::create([
                    'type' => 'whatsapp',
                    'recipient' => $phone,
                    'status' => 'success',
                    'context' => $context,
                ]);
            }
            $response = Http::withToken($token)
                ->acceptJson()
                ->post("https://graph.facebook.com/{$apiVersion}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => "56966291494",
                    'type' => 'template',
                    'template' => [
                        'name' => $templateToUse,
                        'language' => [
                            'code' => 'es',
                        ],
                        'components' => $components,
                    ],
                ]);
                 if (! $response->successful()) {
                Log::error('Report notification: WhatsApp template send failed', $context + [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                NotificationLog::create([
                    'type' => 'whatsapp',
                    'recipient' => $phone,
                    'status' => 'failure',
                    'message' => 'Template send failed: ' . $response->body(),
                    'context' => $context,
                ]);
            } else {
                Log::info('Report notification: WhatsApp template sent', $context);

                NotificationLog::create([
                    'type' => 'whatsapp',
                    'recipient' => $phone,
                    'status' => 'success',
                    'context' => $context,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Report notification: WhatsApp template exception', $context + [
                'error' => $e->getMessage(),
            ]);

            NotificationLog::create([
                'type' => 'whatsapp',
                'recipient' => $phone,
                'status' => 'failure',
                'message' => 'Exception: ' . $e->getMessage(),
                'context' => $context,
            ]);
        }
    }

    private function sendWhatsappTextMessage(string $phone, string $body, array $context): void
    {
        $token = config('process_notifications.whatsapp.token');
        $phoneId = config('process_notifications.whatsapp.phone_id');
        $apiVersion = config('process_notifications.whatsapp.api_version', 'v18.0');

        if (empty($token) || empty($phoneId)) {
            Log::warning('Report notification: WhatsApp credentials missing', $context);

            return;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->post("https://graph.facebook.com/{$apiVersion}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => true,
                        'body' => $body,
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('Report notification: WhatsApp text send failed', $context + [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                NotificationLog::create([
                    'type' => 'whatsapp',
                    'recipient' => $phone,
                    'status' => 'failure',
                    'message' => 'Text send failed: ' . $response->body(),
                    'context' => $context,
                ]);
            } else {
                Log::info('Report notification: WhatsApp text sent', $context);

                NotificationLog::create([
                    'type' => 'whatsapp',
                    'recipient' => $phone,
                    'status' => 'success',
                    'context' => $context,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Report notification: WhatsApp text exception', $context + [
                'error' => $e->getMessage(),
            ]);

            NotificationLog::create([
                'type' => 'whatsapp',
                'recipient' => $phone,
                'status' => 'failure',
                'message' => 'Exception: ' . $e->getMessage(),
                'context' => $context,
            ]);
        }
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '56')) {
            $digits = '56' . substr($digits, -9);
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            $digits = '56' . $digits;
        } elseif (strlen($digits) === 8) {
            $digits = '569' . $digits;
        }

        return $digits;
    }

    private function sendEmail(string $emailRecipient, Mailable $mailable, array $context): void
    {
        $bccRecipients = array_filter([
            'carlos.alvarez@greenex.cl',
        ]);

        // Avoid BCCing the primary recipient if they are the same
        $bccRecipients = array_diff($bccRecipients, [$emailRecipient]);

        try {

            $mail = Mail::to($emailRecipient);
            if (! empty($bccRecipients)) {
                $mail->bcc($bccRecipients);
            }
            $mail->send($mailable);
            if($emailRecipient=="Antonio.barros@alkasa.cl"){
                $mail = Mail::to("diego.ayala@alkasa.cl");
                $mail->send($mailable);
            }
            Log::info('Report notification: email sent', $context + [
                'email' => $emailRecipient,
                'bcc' => $bccRecipients,
            ]);

            NotificationLog::create([
                'type' => 'email',
                'recipient' => $emailRecipient,
                'subject' => get_class($mailable),
                'status' => 'success',
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            Log::error('Report notification: email send failed', $context + [
                'email' => $emailRecipient,
                'error' => $e->getMessage(),
            ]);

            NotificationLog::create([
                'type' => 'email',
                'recipient' => $emailRecipient,
                'subject' => get_class($mailable),
                'status' => 'failure',
                'message' => $e->getMessage(),
                'context' => $context,
            ]);
        }
    }

    private function buildProcessWhatsappBody(?string $producerName, ?string $numeroProceso, ?string $formattedDate, ?string $reportUrl): string
    {
        $greetingName = $producerName ?: 'Estimado(a)';
        $dateSnippet = $formattedDate ? " del {$formattedDate}" : '';
        $message = "Hola {$greetingName}, ya esta disponible el informe del proceso {$numeroProceso}{$dateSnippet}.";

        if ($reportUrl) {
            $message .= " Puedes acceder al documento aqui: {$reportUrl}";
        } else {
            $message .= ' Comunicate con Greenex para obtener el documento.';
        }

        return $message;
    }

    private function buildReceptionPreviewWhatsappBody(?string $producerName, ?string $numeroRecepcion, ?string $formattedDate, ?string $previewUrl): string
    {
        $greetingName = $producerName ?: 'Equipo';
        $dateSnippet = $formattedDate ? " del {$formattedDate}" : '';
        $message = "Hola {$greetingName}, se ha generado un informe de previsualizacion para la recepcion {$numeroRecepcion}{$dateSnippet}.";

        if ($previewUrl) {
            $message .= " Puedes revisarlo en: {$previewUrl}";
        }

        $message .= ' Una vez validado, por favor aprueba el informe en la plataforma.';

        return $message;
    }

    public function sendPreviewReportWhatsapp(
        Recepcion $recepcion,
        array $phones,
        ?string $previewUrl,
        ?string $pdfAbsolutePath = null,
        ?string $pdfFilename = null
    ): void
    {

        $context = [
            'channel' => 'recepcion',
            'recepcion_id' => $recepcion->id,
            'numero_g_recepcion' => $recepcion->numero_g_recepcion,
        ];

        $phoneCollection = collect($phones);

        if ($phoneCollection->isEmpty()) {
            Log::info('Preview report WhatsApp: no phone numbers provided', $context);
            return;
        }
        Log::info('Preview report WhatsApp: WhatsApp recipients resolved', $context + [
            'phones' => $phoneCollection->values()->all(),
            'phone_count' => $phoneCollection->count(),
        ]);
        Log::info('Preview report WhatsApp: sending', $context);
        $formattedDate = $this->formatDate($recepcion->fecha_g_recepcion);
        $message = $this->buildReceptionPreviewWhatsappBody(
            $recepcion->n_emisor,
            $recepcion->numero_g_recepcion,
            $formattedDate,
            $previewUrl
        );

        $safeFilename = $this->sanitizeFilename(
            $pdfFilename ?: ('reporte_recepcion_' . $recepcion->numero_g_recepcion . '_preview.pdf')
        );

        $documentLink = null;
        $storedRelativePath = null;
        if ($pdfAbsolutePath && is_file($pdfAbsolutePath)) {
            $storedRelativePath = 'preview_reports/' . Carbon::now()->format('YmdHis') . '_' . uniqid('preview_', false) . '_' . $safeFilename;
            try {
                $contents = @file_get_contents($pdfAbsolutePath);
                if ($contents === false) {
                    throw new \RuntimeException('No fue posible leer el archivo temporal del reporte.');
                }

                Storage::disk('public')->put($storedRelativePath, $contents);
                $documentLink = $this->resolvePublicUrlFromDisk($storedRelativePath);
            } catch (\Throwable $e) {
                Log::error('Preview report WhatsApp: failed to prepare PDF attachment', $context + [
                    'source_path' => $pdfAbsolutePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $documentLink) {
            Log::warning('Preview report WhatsApp: document link unavailable, falling back to text only message', $context);
        }

        $template = config('process_notifications.whatsapp.templates.preview')
            ?: config('process_notifications.whatsapp.templates.reception', 'recepcion');

        $this->sendWhatsappNotifications(
            $phoneCollection,
            [
                'template' => $template,
                'document_link' => $documentLink,
                'filename' => $safeFilename,
                'body' => $message,
                'body_params' => array_values(array_filter([
                    $recepcion->numero_g_recepcion ? 'Recepción #' . $recepcion->numero_g_recepcion : null,
                    $previewUrl,
                ])),
            ],
            $context + [
                'preview_url' => $previewUrl,
                'document_link' => $documentLink,
            ]
        );

        Log::info('Preview report WhatsApp: message sent', $context + [
            'preview_url' => $previewUrl,
            'document_link' => $documentLink,
        ], $phoneCollection);

        if ($storedRelativePath) {
            try {
                Storage::disk('public')->delete($storedRelativePath);
            } catch (\Throwable $e) {
                Log::warning('Preview report WhatsApp: failed to delete temporary WhatsApp attachment', $context + [
                    'stored_path' => $storedRelativePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildReceptionWhatsappBody(?string $producerName, ?string $numeroRecepcion, ?string $formattedDate, ?string $reportUrl): string
    {
        $greetingName = $producerName ?: 'Estimado(a)';
        $dateSnippet = $formattedDate ? " del {$formattedDate}" : '';
        $message = "Hola {$greetingName}, ya esta disponible el informe de recepcion {$numeroRecepcion}{$dateSnippet}.";

        if ($reportUrl) {
            $message .= " Puedes acceder al documento aqui: {$reportUrl}";
        } else {
            $message .= ' Comunicate con Greenex para obtener el documento.';
        }

        return $message;
    }
    private function getDistributionList(): array
    {
        return [
           'portal@greenex.cl'
        ];
    }

    private function sendEmailToDistributionList(Mailable $mailable, array $context): void
    {
        $recipients = array_values(array_filter($this->getDistributionList()));

        if (empty($recipients)) {
            Log::info('Report notification: distribution list is empty, skipping send', $context);

            return;
        }

        $chunks = array_chunk($recipients, 5);

        foreach ($chunks as $index => $chunk) {
            $primary = array_shift($chunk);
            if (! $primary) {
                continue;
            }

            try {
                $mail = Mail::to($primary);

            // Clone the mailable to avoid state sharing across sends
            $mail->send(clone $mailable);

            Log::info('Report notification: distribution list email sent', $context + [
                'batch' => $index + 1,
                'to' => $primary,
                'bcc' => $chunk,
            ]);
        } catch (\Throwable $e) {
            Log::error('Report notification: failed to send distribution list email', $context + [
                'batch' => $index + 1,
                'to' => $primary,
                'bcc' => $chunk,
                'error' => $e->getMessage(),
            ]);
        }

            // Send a verification WhatsApp message for each batch
            $verificationPhone = '+56966291494';
            $body = $this->buildDistributionWhatsappBody($context);
            $this->sendWhatsappTextMessage($verificationPhone, $body, $context + [
                'batch' => $index + 1,
                'verification_phone' => $verificationPhone,
            ]);
        }
    }

    private function buildDistributionWhatsappBody(array $context): string
    {
        $channel = $context['channel'] ?? 'reporte';
        $id = $context['n_proceso'] ?? $context['numero_g_recepcion'] ?? $context['recepcion_id'] ?? $context['proceso_id'] ?? '';
        $url = $context['report_url'] ?? $context['report_path'] ?? '';

        $label = $channel === 'process' ? 'proceso' : 'recepcion';
        $idPart = $id ? " #{$id}" : '';
        $urlPart = $url ? " Link: {$url}" : '';

        return "Envio automatizado de {$label}{$idPart} a lista de distribucion.{$urlPart}";
    }
}
