<?php

namespace App\Jobs;

use App\Services\Inventory\TransformationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTransformationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected array $data,
        protected int $userId
    ) {}

    public function handle(TransformationService $transformationService): void
    {
        $transformationService->transform($this->data, $this->userId);
    }
}
