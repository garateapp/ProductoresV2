<?php

namespace App\Services\Inventory;

use App\Models\InventoryTechnicalSheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TechnicalSheetService
{
    public function create(array $data, int $userId): InventoryTechnicalSheet
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($data, $userId, &$storedFiles): InventoryTechnicalSheet {
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
                    'nombre' => trim((string) $data['nombre']),
                    'version' => $nextVersion,
                    'fecha_vigencia_desde' => $data['fecha_vigencia_desde'],
                    'fecha_vigencia_hasta' => $data['fecha_vigencia_hasta'] ?: null,
                    'activo' => (bool) ($data['activo'] ?? true),
                    'observacion' => $data['observacion'] ?: null,
                    'created_by' => $userId,
                    'metadata' => $this->metadataFor($data, $isSemielaborado),
                ]);

                $this->syncDetails($sheet, $data);
                $this->syncImages($sheet, $data, $storedFiles);

                return $sheet->fresh(['packaging', 'material', 'creator', 'unitItems.material', 'palletItems.material', 'images']);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function update(InventoryTechnicalSheet $sheet, array $data): InventoryTechnicalSheet
    {
        $storedFiles = [];
        $filesToDelete = [];

        try {
            $updatedSheet = DB::transaction(function () use ($sheet, $data, &$storedFiles, &$filesToDelete): InventoryTechnicalSheet {
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
                    'nombre' => trim((string) $data['nombre']),
                    'fecha_vigencia_desde' => $data['fecha_vigencia_desde'],
                    'fecha_vigencia_hasta' => $data['fecha_vigencia_hasta'] ?: null,
                    'activo' => (bool) ($data['activo'] ?? true),
                    'observacion' => $data['observacion'] ?: null,
                    'metadata' => $this->metadataFor($data, $isSemielaborado, $sheet->metadata ?? []),
                ])->save();

                $this->syncDetails($sheet, $data);
                $this->syncImages($sheet, $data, $storedFiles, $filesToDelete);

                return $sheet->fresh(['packaging', 'material', 'creator', 'unitItems.material', 'palletItems.material', 'images']);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedFiles);
            throw $exception;
        }

        $this->deleteStoredFiles($filesToDelete);

        return $updatedSheet;
    }

    private function metadataFor(array $data, bool $isSemielaborado, array $current = []): array
    {
        if ($isSemielaborado) {
            unset($current['packaging_spec']);

            return $current;
        }

        $current['packaging_spec'] = $data['packaging_spec'] ?? [];

        return $current;
    }

    private function syncImages(
        InventoryTechnicalSheet $sheet,
        array $data,
        array &$storedFiles,
        array &$filesToDelete = []
    ): void {
        $existingImages = collect($data['existing_images'] ?? []);
        $removedIds = collect($data['removed_image_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $referencedIds = $existingImages->pluck('id')->map(fn ($id) => (int) $id)->merge($removedIds)->unique();

        if ($referencedIds->isNotEmpty()) {
            $ownedIds = $sheet->images()->whereKey($referencedIds)->pluck('id');
            if ($ownedIds->count() !== $referencedIds->count()) {
                throw ValidationException::withMessages([
                    'existing_images' => 'Una o más imágenes no pertenecen a esta ficha técnica.',
                ]);
            }
        }

        foreach ($existingImages as $index => $imageData) {
            if ($removedIds->contains((int) $imageData['id'])) {
                continue;
            }

            $sheet->images()->whereKey((int) $imageData['id'])->update([
                'descripcion' => trim((string) $imageData['descripcion']),
                'orden' => (int) ($imageData['orden'] ?? $index),
            ]);
        }

        if ($removedIds->isNotEmpty()) {
            $images = $sheet->images()->whereKey($removedIds)->get();
            foreach ($images as $image) {
                $filesToDelete[] = ['disk' => $image->disk, 'path' => $image->path];
            }
            $sheet->images()->whereKey($removedIds)->delete();
        }

        foreach ($data['new_images'] ?? [] as $index => $imageData) {
            $file = $imageData['file'];
            $disk = 'public';
            $path = $file->store('inventory/technical-sheets/'.$sheet->id, $disk);

            if (! is_string($path) || $path === '') {
                throw new RuntimeException('No fue posible almacenar una imagen de la ficha técnica.');
            }

            $storedFiles[] = ['disk' => $disk, 'path' => $path];
            $sheet->images()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'descripcion' => trim((string) $imageData['descripcion']),
                'orden' => (int) ($imageData['orden'] ?? ($existingImages->count() + $index)),
            ]);
        }
    }

    private function deleteStoredFiles(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }
    }

    private function syncDetails(InventoryTechnicalSheet $sheet, array $data): void
    {
        $unitItems = collect($data['unit_items'] ?? [])
            ->filter(fn (array $item) => (int) ($item['material_id'] ?? 0) > 0 && (float) ($item['cantidad_estandar'] ?? 0) > 0)
            ->map(fn (array $item) => [
                'material_id' => (int) $item['material_id'],
                'replacement_material_id' => (int) ($item['replacement_material_id'] ?? 0) ?: null,
                'cantidad_estandar' => (float) $item['cantidad_estandar'],
                'calibre' => ! empty($item['calibre']) ? trim($item['calibre']) : null,
            ])
            ->values()
            ->all();

        $palletItems = collect($data['pallet_items'] ?? [])
            ->filter(fn (array $item) => (int) ($item['material_id'] ?? 0) > 0 && (float) ($item['cantidad_estandar'] ?? 0) > 0)
            ->map(fn (array $item) => [
                'material_id' => (int) $item['material_id'],
                'replacement_material_id' => (int) ($item['replacement_material_id'] ?? 0) ?: null,
                'cantidad_estandar' => (float) $item['cantidad_estandar'],
                'calibre' => ! empty($item['calibre']) ? trim($item['calibre']) : null,
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
