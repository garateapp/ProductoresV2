<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EstimationImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use App\Notifications\ImportFailedNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ImportEstimationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $absolutePath,
        public string $storedPath,
        public string $originalName,
        public array $meta,
        public int $userId
    ) {
    }

    public function handle(EstimationImportService $importService): void
    {
        $user = User::findOrFail($this->userId);

        $version = $importService->importCsvFromPath(
            $this->absolutePath,
            $this->originalName,
            $this->meta,
            $user,
            $this->storedPath
        );

        $key = 'import_feedback:'.$this->userId;
        $feedback = Cache::get($key, []);
        $feedback[] = [
            'status' => 'success',
            'type' => 'estimations',
            'label' => $this->meta['type'] ?? 'estimaciones',
            'file' => $this->originalName,
            'message' => 'Importacion completada correctamente.',
            'version_id' => $version->id,
        ];
        Cache::put($key, $feedback, now()->addHours(12));
    }

    public function failed(Throwable $exception): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $user->notify(new ImportFailedNotification(
            'estimations',
            $this->meta['type'] ?? 'estimaciones',
            $this->originalName,
            $exception
        ));

        $message = $exception instanceof ValidationException
            ? implode(' | ', Arr::flatten($exception->errors()))
            : $exception->getMessage();

        $key = 'import_errors:'.$this->userId;
        $errors = Cache::get($key, []);
        $errors[] = [
            'type' => 'estimations',
            'label' => $this->meta['type'] ?? 'estimaciones',
            'file' => $this->originalName,
            'message' => $message,
        ];
        Cache::put($key, $errors, now()->addHours(12));

        $feedbackKey = 'import_feedback:'.$this->userId;
        $feedback = Cache::get($feedbackKey, []);
        $feedback[] = [
            'status' => 'error',
            'type' => 'estimations',
            'label' => $this->meta['type'] ?? 'estimaciones',
            'file' => $this->originalName,
            'message' => $message,
        ];
        Cache::put($feedbackKey, $feedback, now()->addHours(12));
    }
}
