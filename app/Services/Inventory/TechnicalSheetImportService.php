<?php

namespace App\Services\Inventory;

use App\Models\InventoryMaterial;
use App\Models\InventoryPackaging;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TechnicalSheetImportService
{
    /**
     * Parse Excel and create fichas técnicas in bulk.
     *
     * @return array{created: int, errors: list<string>}
     */
    public function importFromExcel(string $filePath, int $userId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        if (! $spreadsheet) {
            throw ValidationException::withMessages(['file' => ['No se pudo leer el archivo Excel.']]);
        }

        $encabezados = $this->parseEncabezados($spreadsheet);
        $unitItems = $this->parseMaterialItems($spreadsheet, 'Material por Unidad de Caja');
        $palletItems = $this->parseMaterialItems($spreadsheet, 'Material por Pallet');

        $packagingMap = InventoryPackaging::query()->where('activo', true)->get(['id', 'codigo'])->keyBy(fn ($p) => mb_strtolower(trim($p->codigo)));
        $materialMap = InventoryMaterial::query()->where('activo', true)->get(['id', 'codigo', 'nombre'])->keyBy(fn ($m) => mb_strtolower(trim($m->codigo)));

        $created = 0;
        $errors = [];

        foreach ($encabezados as $rowIndex => $header) {
            $rowErrors = [];
            $isSemielaborado = mb_strtolower(trim($header['es_semielaborado'] ?? '')) === 'si';
            $targetCode = trim($header['codigo_embalaje'] ?? '');
            $semielaboradoCode = trim($header['material_semielaborado'] ?? '');
            $nombre = trim($header['nombre'] ?? '');

            if ($nombre === '') {
                $rowErrors[] = 'El nombre de la ficha es obligatorio.';
            }

            if ($isSemielaborado) {
                if ($semielaboradoCode === '') {
                    $rowErrors[] = 'Si es semielaborado, debe indicar el código del material.';
                } elseif (! isset($materialMap[mb_strtolower($semielaboradoCode)])) {
                    $rowErrors[] = "Material semielaborado '{$semielaboradoCode}' no encontrado.";
                }
            } else {
                if ($targetCode === '') {
                    $rowErrors[] = 'El código de embalaje es obligatorio.';
                } elseif (! isset($packagingMap[mb_strtolower($targetCode)])) {
                    $rowErrors[] = "Embalaje '{$targetCode}' no encontrado.";
                }
            }

            $fechaDesde = trim($header['fecha_vigencia_desde'] ?? '');
            $fechaHasta = trim($header['fecha_vigencia_hasta'] ?? '');
            $activo = mb_strtolower(trim($header['activo'] ?? 'si')) === 'si';
            $observacion = trim($header['observacion'] ?? '');

            if ($fechaDesde === '') {
                $rowErrors[] = 'La fecha de vigencia desde es obligatoria.';
            } elseif (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
                $rowErrors[] = "Fecha desde '{$fechaDesde}' debe tener formato YYYY-MM-DD.";
            }

            if ($fechaHasta !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
                $rowErrors[] = "Fecha hasta '{$fechaHasta}' debe tener formato YYYY-MM-DD.";
            }

            // Group items by key (same as encabezado code)
            $lookupKey = $isSemielaborado ? mb_strtolower($semielaboradoCode) : mb_strtolower($targetCode);
            $sheetUnitItems = $unitItems->get($lookupKey, collect());
            $sheetPalletItems = $palletItems->get($lookupKey, collect());

            $resolvedUnitItems = $this->resolveItems($sheetUnitItems, $materialMap, $rowErrors, 'Unidad');
            $resolvedPalletItems = $this->resolveItems($sheetPalletItems, $materialMap, $rowErrors, 'Pallet');

            if (empty($resolvedUnitItems) && empty($resolvedPalletItems)) {
                $rowErrors[] = 'Debe tener al menos un material en Unidad o Pallet.';
            }

            if (! empty($rowErrors)) {
                $label = $targetCode ?: $semielaboradoCode;
                $errors[] = "Fila {$rowIndex} ({$label}): ".implode(' | ', $rowErrors);

                continue;
            }

            try {
                $sheetData = [
                    'nombre' => $nombre,
                    'es_semielaborado' => $isSemielaborado,
                    'packaging_id' => $isSemielaborado ? null : $packagingMap[mb_strtolower($targetCode)]->id,
                    'material_id' => $isSemielaborado ? $materialMap[mb_strtolower($semielaboradoCode)]->id : null,
                    'fecha_vigencia_desde' => $fechaDesde,
                    'fecha_vigencia_hasta' => $fechaHasta ?: null,
                    'activo' => $activo,
                    'observacion' => $observacion ?: null,
                    'unit_items' => $resolvedUnitItems,
                    'pallet_items' => $resolvedPalletItems,
                ];

                $sheetService = app(TechnicalSheetService::class);
                $sheetService->create($sheetData, $userId);
                $created++;
            } catch (ValidationException $e) {
                $label = $targetCode ?: $semielaboradoCode;
                $errors[] = "Fila {$rowIndex} ({$label}): ".implode(' | ', array_values($e->errors()));
            } catch (\Throwable $e) {
                $label = $targetCode ?: $semielaboradoCode;
                $errors[] = "Fila {$rowIndex} ({$label}): Error inesperado - ".$e->getMessage();
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return ['created' => $created, 'errors' => $errors];
    }

    private function parseEncabezados($spreadsheet): Collection
    {
        $sheet = $spreadsheet->getSheetByName('Embalaje');
        if (! $sheet) {
            throw ValidationException::withMessages(['file' => ['La hoja "Embalaje" no fue encontrada en el archivo.']]);
        }

        $rows = collect();
        $highestRow = $sheet->getHighestRow();

        // Find header row by looking for 'CÓDIGO EMBALAJE'
        $headerRow = null;
        for ($r = 1; $r <= min($highestRow, 15); $r++) {
            $cellValue = trim((string) $sheet->getCell('A'.$r)->getValue());
            if (mb_strtoupper($cellValue) === 'CÓDIGO EMBALAJE' || mb_strtoupper($cellValue) === 'CODIGO EMBALAJE') {
                $headerRow = $r;
                break;
            }
        }

        if (! $headerRow) {
            throw ValidationException::withMessages(['file' => ['No se encontró la fila de encabezados en la hoja "Embalaje".']]);
        }

        $hasNameColumn = str_contains(mb_strtoupper(trim((string) $sheet->getCell('B'.$headerRow)->getValue())), 'NOMBRE');

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $firstCell = trim((string) $sheet->getCell('A'.$r)->getValue());
            $semielaboradoCell = (string) $sheet->getCell(($hasNameColumn ? 'C' : 'B').$r)->getValue();
            $materialSemielaboradoCell = trim((string) $sheet->getCell(($hasNameColumn ? 'D' : 'C').$r)->getValue());

            if ($firstCell === '' && $materialSemielaboradoCell === '') {
                continue;
            }

            $rows->put($r, [
                'codigo_embalaje' => $firstCell,
                'nombre' => $hasNameColumn
                    ? (string) $sheet->getCell('B'.$r)->getValue()
                    : 'Ficha '.($firstCell ?: $materialSemielaboradoCell),
                'es_semielaborado' => $semielaboradoCell,
                'material_semielaborado' => $materialSemielaboradoCell,
                'fecha_vigencia_desde' => $this->extractDateValue($sheet->getCell(($hasNameColumn ? 'E' : 'D').$r)->getValue()),
                'fecha_vigencia_hasta' => $this->extractDateValue($sheet->getCell(($hasNameColumn ? 'F' : 'E').$r)->getValue()),
                'activo' => (string) $sheet->getCell(($hasNameColumn ? 'G' : 'F').$r)->getValue(),
                'observacion' => (string) $sheet->getCell(($hasNameColumn ? 'H' : 'G').$r)->getValue(),
            ]);
        }

        return $rows;
    }

    private function parseMaterialItems($spreadsheet, string $sheetName): Collection
    {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (! $sheet) {
            return collect();
        }

        $rows = collect();
        $highestRow = $sheet->getHighestRow();

        // Find header row
        $headerRow = null;
        for ($r = 1; $r <= min($highestRow, 10); $r++) {
            $cellValue = trim((string) $sheet->getCell('A'.$r)->getValue());
            if (str_contains(mb_strtoupper($cellValue), 'EMBALAJE') || str_contains(mb_strtoupper($cellValue), 'SEMIELABORADO')) {
                $headerRow = $r;
                break;
            }
        }

        if (! $headerRow) {
            return collect();
        }

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $groupCode = trim((string) $sheet->getCell('A'.$r)->getValue());
            if ($groupCode === '') {
                continue;
            }

            $materialCode = trim((string) $sheet->getCell('B'.$r)->getValue());
            if ($materialCode === '') {
                continue;
            }

            $key = mb_strtolower($groupCode);
            $rows->push([
                'group_key' => $key,
                'material_codigo' => $materialCode,
                'replacement_codigo' => trim((string) $sheet->getCell('C'.$r)->getValue()),
                'cantidad_estandar' => (string) $sheet->getCell('D'.$r)->getValue(),
                'calibre' => trim((string) $sheet->getCell('E'.$r)->getValue()),
            ]);
        }

        return $rows->groupBy('group_key');
    }

    private function resolveItems(Collection $items, Collection $materialMap, array &$rowErrors, string $type): array
    {
        $resolved = [];

        foreach ($items as $item) {
            $materialCode = mb_strtolower(trim($item['material_codigo']));
            if (! isset($materialMap[$materialCode])) {
                $rowErrors[] = "{$type}: Material '{$item['material_codigo']}' no encontrado.";

                continue;
            }

            $replacementId = null;
            if (! empty($item['replacement_codigo'])) {
                $replacementCode = mb_strtolower(trim($item['replacement_codigo']));
                if (! isset($materialMap[$replacementCode])) {
                    $rowErrors[] = "{$type}: Material reemplazo '{$item['replacement_codigo']}' no encontrado.";

                    continue;
                }
                $replacementId = $materialMap[$replacementCode]->id;
            }

            $cantidad = (float) ($item['cantidad_estandar'] ?? 0);
            if ($cantidad <= 0) {
                $rowErrors[] = "{$type}: La cantidad estándar debe ser mayor a 0.";

                continue;
            }

            $resolved[] = [
                'material_id' => $materialMap[$materialCode]->id,
                'replacement_material_id' => $replacementId,
                'cantidad_estandar' => $cantidad,
                'calibre' => ! empty($item['calibre']) ? $item['calibre'] : null,
            ];
        }

        return $resolved;
    }

    private function extractDateValue($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // Handle numeric Excel date (days since 1900)
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        }

        return trim((string) $value);
    }
}
