<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PreviewExcel extends Command
{
    protected $signature = 'excel:preview {path : Path to the Excel file} {--sheet= : Sheet name or index (0-based)} {--rows=20 : Number of rows to preview}';

    protected $description = 'Preview an Excel file (sheet names and first N rows) as JSON output';

    public function handle(): int
    {
        $path = $this->argument('path');
        $sheetOption = $this->option('sheet');
        $limit = (int) $this->option('rows');

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        try {
            $spreadsheet = IOFactory::load($path);
            $sheetNames = $spreadsheet->getSheetNames();

            // Resolve sheet
            if ($sheetOption === null || $sheetOption === '') {
                $sheet = $spreadsheet->getActiveSheet();
            } elseif (is_numeric($sheetOption)) {
                $sheet = $spreadsheet->getSheet((int) $sheetOption);
            } else {
                $sheet = $spreadsheet->getSheetByName($sheetOption) ?? $spreadsheet->getActiveSheet();
            }

            $rows = $sheet->toArray(null, true, true, true);
            $preview = array_slice($rows, 0, $limit);

            $output = [
                'sheets' => $sheetNames,
                'selectedSheet' => $sheet->getTitle(),
                'rowCount' => count($rows),
                'preview' => $preview,
            ];

            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error reading Excel: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

