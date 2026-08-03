<?php

namespace App\Services\Integrations\Exporters;

use App\Contracts\Integrations\OutputExporterInterface;
use Illuminate\Support\Facades\Storage;

class JsonExporter implements OutputExporterInterface
{
    private array $records = [];
    private string $filePath;
    private string $disk;
    private bool $initialized = false;

    public function key(): string
    {
        return 'json';
    }

    public function label(): string
    {
        return 'JSON';
    }

    public function validateConfiguration(array $configuration): void
    {
    }

    public function initialize(array $configuration, array $headers): string
    {
        $this->disk = $configuration['disk'] ?? 'local';
        $filename = $configuration['filename'] ?? 'export_' . now()->format('Ymd_His') . '.json';
        $this->filePath = 'integrations/exports/' . $filename;
        $this->records = [];
        $this->initialized = true;

        return $this->filePath;
    }

    public function writeHeaders(array $headers): void
    {
    }

    public function writeRecord(array $record): void
    {
        $this->records[] = $record;
    }

    public function finalize(): string
    {
        $json = json_encode($this->records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        Storage::disk($this->disk)->put($this->filePath, $json);
        $this->records = [];

        return $this->filePath;
    }

    public function cancel(): void
    {
        $this->records = [];
        if (isset($this->filePath)) {
            Storage::disk($this->disk ?? 'local')->delete($this->filePath);
        }
    }
}
