<?php

namespace App\Jobs;

use App\Services\Inventory\TransformationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

class ProcessTransformationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Las transformaciones no son idempotentes: reintentarlas a ciegas puede duplicar
     * el consumo. Se deja un solo intento para que caigan en failed_jobs y el operador
     * las reintiente desde la pantalla de consumos pendientes.
     */
    public int $tries = 1;

    public function __construct(
        protected array $data,
        protected int $userId
    ) {}

    public function handle(TransformationService $transformationService): void
    {
        $transformationService->transform($this->data, $this->userId);
    }

    /**
     * Permite recuperar los datos desde el payload serializado de la tabla jobs
     * para poder reejecutar la transformación de forma controlada.
     */
    public function payload(): array
    {
        return [
            'data' => $this->data,
            'user_id' => $this->userId,
        ];
    }

    public function failed(?\Throwable $exception): void
    {
        report($exception);
    }

    public static function dataForRetry(array $payload): array
    {
        $command = $payload['data']['command'] ?? null;

        if (! is_string($command)) {
            throw new ValidationException('El trabajo en cola no tiene un payload válido.');
        }

        $instance = str_starts_with($command, 'O:')
            ? @unserialize($command)
            : @unserialize(app('encrypter')->decrypt($command));

        if (! $instance instanceof self) {
            throw new ValidationException('No se pudo recuperar el trabajo de transformación.');
        }

        return $instance->payload();
    }
}