<?php

namespace App\Services\Integrations\Exporters;

use App\Contracts\Integrations\OutputExporterInterface;
use Illuminate\Support\Facades\Storage;

class ExcelExporter implements OutputExporterInterface
{
    private array $headers = [];
    private array $rows = [];
    private string $filePath;
    private string $disk;

    public function key(): string
    {
        return 'excel';
    }

    public function label(): string
    {
        return 'Excel';
    }

    public function validateConfiguration(array $configuration): void
    {
    }

    public function initialize(array $configuration, array $headers): string
    {
        $this->disk = $configuration['disk'] ?? 'local';
        $filename = $configuration['filename'] ?? 'export_' . now()->format('Ymd_His') . '.xlsx';
        $this->filePath = 'integrations/exports/' . $filename;
        $this->headers = $headers;
        $this->rows = [];

        return $this->filePath;
    }

    public function writeHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function writeRecord(array $record): void
    {
        $row = [];
        foreach ($this->headers as $header) {
            $row[$header] = $record[$header] ?? '';
        }
        $this->rows[] = $row;
    }

    public function finalize(): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $col = 1;
        foreach ($this->headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }

        $rowNum = 2;
        foreach ($this->rows as $row) {
            $col = 1;
            foreach ($this->headers as $header) {
                $sheet->setCellValueByColumnAndRow($col, $rowNum, $row[$header] ?? '');
                $col++;
            }
            $rowNum++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'excel_');
        $writer->save($tempPath);

        Storage::disk($this->disk)->put($this->filePath, file_get_contents($tempPath));
        unlink($tempPath);
        $spreadsheet->disconnectWorksheets();

        return $this->filePath;
    }

    public function cancel(): void
    {
        $this->rows = [];
        $this->headers = [];

        if (isset($this->filePath) && Storage::disk($this->disk ?? 'local')->exists($this->filePath)) {
            Storage::disk($this->disk ?? 'local')->delete($this->filePath);
        }
    }
}
