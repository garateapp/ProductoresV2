<?php

namespace App\Console\Commands;

use App\Services\Inventory\LogisticUnitService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class ImportInventoryPilotLogisticUnitsCommand extends Command
{
    protected $signature = 'inventory:pilot-import-logistic-units {file} {--user-id=} {--dry-run}';

    protected $description = 'Importa pallets/LPN de piloto desde un archivo CSV';

    public function handle(LogisticUnitService $logisticUnitService): int
    {
        $file = (string) $this->argument('file');

        if (! file_exists($file)) {
            $this->error('No existe el archivo indicado.');
            return self::FAILURE;
        }

        $userId = (int) ($this->option('user-id') ?: 1);
        $handle = fopen($file, 'rb');

        if (! $handle) {
            $this->error('No fue posible abrir el archivo.');
            return self::FAILURE;
        }

        $headers = fgetcsv($handle, 0, ';');
        if (! is_array($headers)) {
            fclose($handle);
            $this->error('El archivo CSV no tiene encabezados válidos.');
            return self::FAILURE;
        }

        $created = 0;
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $line++;
            $data = array_combine($headers, $row);

            if (! is_array($data)) {
                $errors[] = "Línea {$line}: columnas inválidas.";
                continue;
            }

            $payload = [
                'license_plate_number' => trim((string) ($data['license_plate_number'] ?? '')),
                'material_id' => (int) ($data['material_id'] ?? 0),
                'current_location_id' => ($data['current_location_id'] ?? '') !== '' ? (int) $data['current_location_id'] : null,
                'base_quantity' => (float) ($data['base_quantity'] ?? 0),
                'available_quantity' => (float) ($data['available_quantity'] ?? 0),
                'unit_id' => ($data['unit_id'] ?? '') !== '' ? (int) $data['unit_id'] : null,
                'lot_code' => ($data['lot_code'] ?? '') !== '' ? (string) $data['lot_code'] : null,
                'supplier_lot' => ($data['supplier_lot'] ?? '') !== '' ? (string) $data['supplier_lot'] : null,
                'production_batch' => ($data['production_batch'] ?? '') !== '' ? (string) $data['production_batch'] : null,
                'reference_type' => ($data['reference_type'] ?? '') !== '' ? (string) $data['reference_type'] : null,
                'reference_id' => ($data['reference_id'] ?? '') !== '' ? (int) $data['reference_id'] : null,
                'received_at' => ($data['received_at'] ?? '') !== '' ? (string) $data['received_at'] : null,
                'status' => ($data['status'] ?? '') !== '' ? (string) $data['status'] : 'active',
            ];

            $validator = Validator::make($payload, [
                'license_plate_number' => ['required', 'string', 'max:100', 'unique:inventory_logistic_units,license_plate_number'],
                'material_id' => ['required', 'integer', 'exists:inventory_materials,id'],
                'current_location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
                'base_quantity' => ['required', 'numeric', 'gt:0'],
                'available_quantity' => ['required', 'numeric', 'gte:0'],
                'unit_id' => ['nullable', 'integer', 'exists:inventory_units,id'],
                'received_at' => ['nullable', 'date'],
                'status' => ['required', 'in:active,consumed,waste,blocked,closed'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Línea {$line}: ".collect($validator->errors()->all())->implode(' | ');
                continue;
            }

            if (! $this->option('dry-run')) {
                $logisticUnitService->create($validator->validated(), $userId);
            }

            $created++;
        }

        fclose($handle);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->warn($error);
            }
        }

        $this->info($this->option('dry-run') ? 'Validación terminada.' : 'Importación terminada.');
        $this->line('Filas válidas: '.$created);
        $this->line('Errores: '.count($errors));

        return $errors === [] ? self::SUCCESS : self::INVALID;
    }
}
