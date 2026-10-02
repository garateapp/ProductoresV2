<?php

namespace App\Services\Inventory;

use App\Exports\CostCenterTemplateExport;
use App\Models\InventoryCostCenter;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CostCenterImportService
{
    /**
     * Parse Excel and upsert centros de costo in bulk.
     *
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function importFromExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        if (! $spreadsheet) {
            throw ValidationException::withMessages(['file' => ['No se pudo leer el archivo Excel.']]);
        }

        try {
            $rows = $this->parseRows($spreadsheet);

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['file' => ['El archivo no contiene filas de datos.']]);
            }

            $services = Service::query()->get(['id', 'name'])->keyBy(
                fn (Service $service) => $this->normalize($service->name)
            );

            $existing = InventoryCostCenter::query()->get(['id', 'codigo'])->keyBy(
                fn (InventoryCostCenter $costCenter) => $this->normalize($costCenter->codigo)
            );

            $created = 0;
            $updated = 0;
            $errors = [];
            $seenInFile = [];

            foreach ($rows as $row) {
                $rowNumber = $row['row'];
                $codigo = mb_strtoupper(trim((string) ($row['codigo'] ?? '')));
                $nombre = trim((string) ($row['nombre'] ?? ''));
                $descripcion = trim((string) ($row['descripcion'] ?? ''));
                $serviceName = trim((string) ($row['servicio'] ?? ''));
                $activo = $this->parseActivo($row['activo'] ?? '');

                $rowErrors = [];

                if ($codigo === '') {
                    $rowErrors[] = 'El código es obligatorio.';
                } elseif (mb_strlen($codigo) > 50) {
                    $rowErrors[] = 'El código no puede superar 50 caracteres.';
                }

                if ($nombre === '') {
                    $rowErrors[] = 'El nombre es obligatorio.';
                } elseif (mb_strlen($nombre) > 150) {
                    $rowErrors[] = 'El nombre no puede superar 150 caracteres.';
                }

                $normalizedCode = $this->normalize($codigo);
                if ($codigo !== '' && isset($seenInFile[$normalizedCode])) {
                    $rowErrors[] = 'El código está repetido en el archivo.';
                }

                if ($descripcion !== '' && mb_strlen($descripcion) > 255) {
                    $rowErrors[] = 'La descripción no puede superar 255 caracteres.';
                }

                $serviceId = null;
                if ($serviceName !== '') {
                    $service = $services->get($this->normalize($serviceName));
                    if (! $service) {
                        $rowErrors[] = "Servicio '{$serviceName}' no encontrado.";
                    } else {
                        $serviceId = $service->id;
                    }
                }

                if (! empty($rowErrors)) {
                    $errors[] = "Fila {$rowNumber}: ".implode(' | ', $rowErrors);

                    continue;
                }

                $payload = [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'descripcion' => $descripcion ?: null,
                    'service_id' => $serviceId,
                    'activo' => $activo,
                ];

                $match = $existing->get($normalizedCode);
                if ($match) {
                    $match->forceFill($payload)->save();
                    $updated++;
                } else {
                    InventoryCostCenter::query()->create($payload);
                    $created++;
                }

                $seenInFile[$normalizedCode] = true;
            }

            return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function parseRows($spreadsheet): Collection
    {
        $sheet = $spreadsheet->getSheetByName(CostCenterTemplateExport::SHEET_NAME)
            ?? $spreadsheet->getSheet(0);

        $highestRow = (int) $sheet->getHighestRow();
        $headerRow = null;

        for ($row = 1; $row <= min($highestRow, 15); $row++) {
            $value = mb_strtoupper(trim((string) $sheet->getCell('A'.$row)->getValue()));
            $value = str_replace(['Á', 'Ó'], ['A', 'O'], $value);

            if ($value === 'CODIGO' || str_contains($value, 'CÓDIGO')) {
                $headerRow = $row;
                break;
            }
        }

        if (! $headerRow) {
            throw ValidationException::withMessages([
                'file' => ['No se encontró la fila de encabezados. Use la plantilla descargada desde el mantenedor.'],
            ]);
        }

        $rows = collect();

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            if ($row === CostCenterTemplateExport::FIRST_DATA_ROW) {
                continue;
            }

            $codigo = trim((string) $sheet->getCell('A'.$row)->getValue());
            $nombre = trim((string) $sheet->getCell('B'.$row)->getValue());

            if ($codigo === '' && $nombre === '') {
                continue;
            }

            $rows->push([
                'row' => (string) $row,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'descripcion' => trim((string) $sheet->getCell('C'.$row)->getValue()),
                'servicio' => trim((string) $sheet->getCell('D'.$row)->getValue()),
                'activo' => trim((string) $sheet->getCell('E'.$row)->getValue()),
            ]);
        }

        return $rows;
    }

    private function parseActivo($value): bool
    {
        $normalized = $this->normalize((string) $value);

        if ($normalized === '') {
            return true;
        }

        return ! in_array($normalized, ['no', '0', 'false', 'n'], true);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return str_replace(' ', '', $value);
    }
}
