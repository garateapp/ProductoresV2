<?php

namespace App\Services\Integrations\Exporters;

use App\Contracts\Integrations\OutputExporterInterface;
use Illuminate\Support\Facades\Storage;

class CsvExporter implements OutputExporterInterface
{
    private $handle;
    private string $filePath;
    private string $disk;

    public function key(): string
    {
        return 'csv';
    }

    public function label(): string
    {
        return 'CSV';
    }

    public function validateConfiguration(array $configuration): void
    {
    }

    public function initialize(array $configuration, array $headers): string
    {
        $this->disk = $configuration['disk'] ?? 'local';
        $filename = $configuration['filename'] ?? 'export_' . now()->format('Ymd_His') . '.csv';
        $this->filePath = 'integrations/exports/' . $filename;

        $stream = Storage::disk($this->disk)->writeStream($this->filePath, tmpfile());
        $this->handle = fopen('php://temp', 'r+');

        if ($configuration['include_bom'] ?? false) {
            fwrite($this->handle, "\xEF\xBB\xBF");
        }

        $delimiter = $configuration['delimiter'] ?? ',';
        $enclosure = $configuration['enclosure'] ?? '"';

        $this->writeRow($headers, $delimiter, $enclosure);

        return $this->filePath;
    }

    public function writeHeaders(array $headers): void
    {
        $delimiter = ',';
        $this->writeRow($headers, $delimiter);
    }

    public function writeRecord(array $record): void
    {
        $delimiter = ',';
        $this->writeRow($record, $delimiter);
    }

    public function finalize(): string
    {
        rewind($this->handle);
        $contents = stream_get_contents($this->handle);
        Storage::disk($this->disk)->put($this->filePath, $contents);
        fclose($this->handle);

        return $this->filePath;
    }

    public function cancel(): void
    {
        if ($this->handle) {
            fclose($this->handle);
        }

        Storage::disk($this->disk ?? 'local')->delete($this->filePath);
    }

    private function writeRow(array $row, string $delimiter = ',', string $enclosure = '"'): void
    {
        fputcsv($this->handle, $row, $delimiter, $enclosure);
    }
}
