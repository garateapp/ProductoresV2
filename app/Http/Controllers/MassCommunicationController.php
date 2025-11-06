<?php

namespace App\Http\Controllers;

use App\Mail\MassCommunicationMail;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MassCommunicationController extends Controller
{
    public function create(): Response
    {
        $this->ensureUserCanSend();

        $services = Service::orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/MassCommunications/Create', [
            'services' => $services,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureUserCanSend();

        $validated = $request->validate([
            'service_id' => ['nullable', 'required_without:manual_recipients', 'exists:services,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'], // 10 MB
            'manual_recipients' => ['nullable', 'string'],
        ]);

        $service = null;
        $serviceName = 'Envío manual';
        $mailServiceName = 'Destinatarios seleccionados';
        $logContextBase = [
            'service_id' => null,
            'service_name' => $serviceName,
        ];

        if (! empty($validated['service_id'])) {
            $service = Service::with([
                'users' => function ($query) {
                    $query->orderBy('name');
                },
                'owner',
                'emails',
            ])->findOrFail($validated['service_id']);

            $serviceName = $service->name;
            $mailServiceName = $serviceName;
            $logContextBase = [
                'service_id' => $service->id,
                'service_name' => $serviceName,
            ];
        }

        $recipientEmails = collect();
        $missingRecipients = [];
        $duplicateRecipients = [];

        $pushEmail = function (?string $email) use (&$recipientEmails) {
            if ($email === null) {
                return 'missing';
            }

            $trimmed = trim($email);
            if ($trimmed === '') {
                return 'missing';
            }

            if (! filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                return 'invalid';
            }

            $normalized = strtolower($trimmed);

            if ($recipientEmails->contains(fn ($item) => $item['normalized'] === $normalized)) {
                return 'duplicate';
            }

            $recipientEmails->push([
                'normalized' => $normalized,
                'original' => $trimmed,
            ]);

            return 'added';
        };

        $handleStatus = function (string $status, array $meta) use (&$missingRecipients, &$duplicateRecipients, $logContextBase) {
            $type = $meta['type'] ?? 'contact';
            $label = $meta['label'] ?? ($meta['name'] ?? $meta['email'] ?? 'Contacto');

            if ($status === 'missing' || $status === 'invalid') {
                $reason = $status === 'missing' ? 'Sin correo registrado' : 'Correo inválido';

                $missingRecipients[] = [
                    'type' => $type,
                    'id' => $meta['id'] ?? null,
                    'name' => $meta['name'] ?? $label,
                    'email' => $meta['email'] ?? null,
                    'reason' => $reason,
                ];

                Log::warning('Mass communication recipient without valid email', $logContextBase + [
                    'recipient_type' => $type,
                    'recipient_id' => $meta['id'] ?? null,
                    'recipient_name' => $meta['name'] ?? null,
                    'recipient_email' => $meta['email'] ?? null,
                    'reason' => $reason,
                ]);
            } elseif ($status === 'duplicate') {
                $duplicateRecipients[] = [
                    'type' => $type,
                    'id' => $meta['id'] ?? null,
                    'name' => $meta['name'] ?? $label,
                    'email' => $meta['email'] ?? null,
                ];

                Log::info('Mass communication duplicate email skipped', $logContextBase + [
                    'recipient_type' => $type,
                    'recipient_id' => $meta['id'] ?? null,
                    'recipient_name' => $meta['name'] ?? null,
                    'recipient_email' => $meta['email'] ?? null,
                ]);
            }
        };

        if ($service) {
            foreach ($service->users as $serviceUser) {
                if (! ($serviceUser->is_active ?? true)) {
                    continue;
                }

                $status = $pushEmail($serviceUser->email);
                $handleStatus($status, [
                    'type' => 'user',
                    'id' => $serviceUser->id,
                    'name' => $serviceUser->name,
                    'email' => $serviceUser->email,
                ]);
            }

            if ($service->owner) {
                $status = $pushEmail($service->owner->email);
                $handleStatus($status, [
                    'type' => 'owner',
                    'id' => $service->owner->id,
                    'name' => $service->owner->name,
                    'email' => $service->owner->email,
                ]);
            }

            foreach ($service->emails as $serviceEmail) {
                $status = $pushEmail($serviceEmail->email);
                $handleStatus($status, [
                    'type' => 'service_email',
                    'id' => $serviceEmail->id,
                    'email' => $serviceEmail->email,
                    'label' => 'Correo de servicio',
                ]);
            }
        }

        if (! empty($validated['manual_recipients'])) {
            $manualEmails = collect(preg_split('/[\s,;,\n\r]+/', $validated['manual_recipients'], -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn ($email) => trim($email))
                ->filter();

            foreach ($manualEmails as $email) {
                $status = $pushEmail($email);
                $handleStatus($status, [
                    'type' => 'manual',
                    'email' => $email,
                    'label' => $email,
                ]);
            }
        }

        $environmentOverrideRecipients = collect();

        if (app()->environment('local')) {
            $overrideRaw = env('MASS_SEND_EMAIL');

            if ($overrideRaw) {
                $overrideEmails = collect(preg_split('/[;,]/', $overrideRaw, -1, PREG_SPLIT_NO_EMPTY))
                    ->map(fn ($email) => trim($email))
                    ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
                    ->unique()
                    ->values();

                if ($overrideEmails->isNotEmpty()) {
                    $environmentOverrideRecipients = $overrideEmails->map(fn ($email) => [
                        'normalized' => strtolower($email),
                        'original' => $email,
                    ]);

                    Log::info('Mass communication using MASS_SEND_EMAIL override', $logContextBase + [
                        'override_recipients' => $overrideEmails->all(),
                    ]);
                } else {
                    Log::warning('MASS_SEND_EMAIL configured but contains no valid addresses', $logContextBase + [
                        'raw_value' => $overrideRaw,
                    ]);
                }
            }
        }

        $finalRecipients = $environmentOverrideRecipients->isNotEmpty()
            ? $environmentOverrideRecipients
            : $recipientEmails;

        if ($finalRecipients->isEmpty()) {
            Log::warning('Mass communication aborted: no valid recipients', $logContextBase + [
                'subject' => $validated['subject'],
                'missing_count' => count($missingRecipients),
                'duplicate_count' => count($duplicateRecipients),
            ]);

            return back()
                ->withErrors([
                    'service_id' => 'No hay correos disponibles para enviar el comunicado.',
                    'manual_recipients' => 'No se encontraron correos válidos para enviar.',
                ])
                ->with('missing_recipients', $missingRecipients)
                ->with('duplicate_recipients', $duplicateRecipients)
                ->with('local_override', $environmentOverrideRecipients->pluck('original')->all())
                ->with('intended_recipient_count', $recipientEmails->count())
                ->withInput();
        }

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('mass-communications');
            $attachmentName = $file->getClientOriginalName();
            $attachmentMime = $file->getMimeType();
        }

        $sentRecipients = [];
        $failedRecipients = [];

        Log::info('Mass communication dispatch started', $logContextBase + [
            'subject' => $validated['subject'],
            'intended_recipient_count' => $recipientEmails->count(),
            'effective_recipient_count' => $finalRecipients->count(),
            'missing_count' => count($missingRecipients),
            'duplicate_count' => count($duplicateRecipients),
            'environment_override' => $environmentOverrideRecipients->pluck('original')->all(),
        ]);

        foreach ($finalRecipients as $recipient) {
            $emailAddress = $recipient['original'];

            try {
                $mailable = new MassCommunicationMail(
                    serviceName: $mailServiceName,
                    subjectLine: $validated['subject'],
                    messageBody: $validated['body'],
                    attachmentPath: $attachmentPath,
                    attachmentName: $attachmentName,
                    attachmentMime: $attachmentMime
                );

                Mail::to($emailAddress)->send($mailable);

                $sentRecipients[] = $emailAddress;

                Log::info('Mass communication email sent', $logContextBase + [
                    'recipient' => $emailAddress,
                ]);
            } catch (\Throwable $exception) {
                $failedRecipients[] = [
                    'email' => $emailAddress,
                    'error' => $exception->getMessage(),
                ];

                Log::error('Mass communication email failed', $logContextBase + [
                    'recipient' => $emailAddress,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($attachmentPath) {
            Storage::delete($attachmentPath);
        }

        Log::info('Mass communication dispatch finished', $logContextBase + [
            'subject' => $validated['subject'],
            'sent_count' => count($sentRecipients),
            'failed_count' => count($failedRecipients),
            'missing_count' => count($missingRecipients),
            'environment_override' => $environmentOverrideRecipients->pluck('original')->all(),
        ]);

        $flashKey = count($sentRecipients) > 0 ? 'success' : 'error';
        $targetDescriptor = $service ? 'del servicio '.$serviceName : 'indicados manualmente';

        $message = count($sentRecipients) > 0
            ? 'Comunicado enviado a '.count($sentRecipients).' destinatarios '.$targetDescriptor.'.'
            : 'No se pudo enviar el comunicado. Revisa los errores registrados.';

        if (count($failedRecipients) > 0) {
            $message .= ' Algunos envíos fallaron.';
        }

        if (count($missingRecipients) > 0) {
            $message .= ' Revisa los contactos sin correo.';
        }

        if ($environmentOverrideRecipients->isNotEmpty()) {
            $message .= ' (Modo local: se usaron destinatarios de MASS_SEND_EMAIL).';
        }

        return redirect()
            ->route('mass-communications.create')
            ->with($flashKey, $message)
            ->with('sent_recipients', $sentRecipients)
            ->with('failed_recipients', $failedRecipients)
            ->with('missing_recipients', $missingRecipients)
            ->with('duplicate_recipients', $duplicateRecipients)
            ->with('local_override', $environmentOverrideRecipients->pluck('original')->all())
            ->with('intended_recipient_count', $recipientEmails->count());
    }

    private function ensureUserCanSend(): void
    {
        $user = Auth::user();

        $allowed = method_exists($user, 'hasRole')
            && ($user->hasRole('Admin') || $user->hasRole('Administrador'));

        abort_unless($allowed, 403);
    }
}
