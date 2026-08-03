<?php

namespace App\Services\Inventory;

use App\Models\InventoryTechnicalSheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TechnicalSheetService
{
    public function create(array $data, int $userId): InventoryTechnicalSheet
    {
        return DB::transaction(function () use ($data, $userId): InventoryTechnicalSheet {
            $isSemielaborado = (bool) ($data['es_semielaborado'] ?? false);
            $targetId = $isSemielaborado ? (int) $data['material_id'] : (int) $data['packaging_id'];

            $this->ensureNoOverlap(
                $targetId,
                (string) $data['fecha_vigencia_desde'],
                $data['fecha_vigencia_hasta'] ?: null,
                $isSemielaborado
            );

            $nextVersion = (int) InventoryTechnicalSheet::query()
                ->where($isSemielaborado ? 'material_id' : 'packaging_id', $targetId)
                ->max('version') + 1;

            $sheet = InventoryTechnicalSheet::create([
                'packaging_id' => $isSemielaborado ? null : $targetId,
                'material_id' => $isSemielaborado ? $targetId : null,
                'es_semielaborado' => $isSemielaborado,
                'version' => $nextVersion,
                'fecha_vigencia_desde' => $data['fecha_vigencia_desde'],
                'fecha_vigencia_hasta' => $data['fecha_vigencia_hasta'] ?: null,
                'activo' => (bool) ($data['activo'] ?? true),
                'observacion' => $data['observacion'] ?: null,
                'created_by' => $userId,
            ]);

            $this->syncDetails($sheet, $data);

            return $sheet->fresh(['packaging', 'material', 'creator', 'unitItems.material', 'palletItems.material']);
        });
    }

    public function update(InventoryTechnicalSheet $sheet, array $data): InventoryTechnicalSheet
    {
        return DB::transaction(function () use ($sheet, $data): InventoryTechnicalSheet {
            $isSemielaborado = (bool) ($data['es_semielaborado'] ?? $sheet->es_semielaborado);
            $targetId = $isSemielaborado ? (int) $data['material_id'] : (int) $data['packaging_id'];

            $this->ensureNoOverlap(
                $targetId,
                (string) $data['fecha_vigencia_desde'],
                $data['fecha_vigencia_hasta'] ?: null,
                $isSemielaborado,
                $sheet->id
            );

            $sheet->fill([
                'packaging_id' => $isSemielaborado ? null : $targetId,
                'material_id' => $isSemielaborado ? $targetId : null,
                'es_semielaborado' => $isSemielaborado,
                'fecha_vigencia_desde' => $data['fecha_vigencia_desde'],
                'fecha_vigencia_hasta' => $data['fecha_vigencia_hasta'] ?: null,
                'activo' => (bool) ($data['activo'] ?? true),
                'observacion' => $data['observacion'] ?: null,
            ])->save();

            $this->syncDetails($sheet, $data);

            return $sheet->fresh(['packaging', 'material', 'creator', 'unitItems.material', 'palletItems.material']);
        });
    }

    private function syncDetails(InventoryTechnicalSheet $sheet, array $data): void
    {
        $unitItems = collect($data['unit_items'] ?? [])
            ->filter(fn (array $item) => (int) ($item['material_id'] ?? 0) > 0 && (float) ($item['cantidad_estandar'] ?? 0) > 0)
            ->map(fn (array $item) => [
                'material_id' => (int) $item['material_id'],
                'replacement_material_id' => (int) ($item['replacement_material_id'] ?? 0) ?: null,
                'cantidad_estandar' => (float) $item['cantidad_estandar'],
                'calibre' => !empty($item['calibre']) ? trim($item['calibre']) : null,
            ])
            ->values()
            ->all();

        $palletItems = collect($data['pallet_items'] ?? [])
            ->filter(fn (array $item) => (int) ($item['material_id'] ?? 0) > 0 && (float) ($item['cantidad_estandar'] ?? 0) > 0)
            ->map(fn (array $item) => [
                'material_id' => (int) $item['material_id'],
                'replacement_material_id' => (int) ($item['replacement_material_id'] ?? 0) ?: null,
                'cantidad_estandar' => (float) $item['cantidad_estandar'],
                'calibre' => !empty($item['calibre']) ? trim($item['calibre']) : null,
            ])
            ->values()
            ->all();

        if (count($unitItems) === 0 && count($palletItems) === 0) {
            throw ValidationException::withMessages([
                'items' => 'La ficha técnica debe tener al menos un material estándar.',
            ]);
        }

        $sheet->unitItems()->delete();
        $sheet->palletItems()->delete();

        foreach ($unitItems as $item) {
            $sheet->unitItems()->create($item);
        }

        foreach ($palletItems as $item) {
            $sheet->palletItems()->create($item);
        }
    }

    private function ensureNoOverlap(int $targetId, string $from, ?string $to, bool $isSemielaborado, ?int $ignoreId = null): void
    {
        $query = InventoryTechnicalSheet::query()
            ->where($isSemielaborado ? 'material_id' : 'packaging_id', $targetId)
            ->where('activo', true)
            ->when($ignoreId, fn ($builder) => $builder->whereKeyNot($ignoreId))
            ->where(function ($builder) use ($from, $to): void {
                $builder->where('fecha_vigencia_desde', '<=', $to ?: '9999-12-31')
                    ->where(function ($endQuery) use ($from): void {
                        $endQuery->whereNull('fecha_vigencia_hasta')
                            ->orWhere('fecha_vigencia_hasta', '>=', $from);
                    });
            });

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'fecha_vigencia_desde' => 'Ya existe una ficha técnica vigente que se superpone con el rango de fechas indicado.',
            ]);
        }
    }
}
