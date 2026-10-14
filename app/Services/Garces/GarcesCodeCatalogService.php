<?php

namespace App\Services\Garces;

use App\Models\GarcesCodeCatalog;
use Illuminate\Support\Collection;

class GarcesCodeCatalogService
{
    public function upsert(string $tipo, string $codigoInterno, ?string $nombre = null, ?string $notas = null, ?int $userId = null): GarcesCodeCatalog
    {
        $codigoInterno = trim($codigoInterno);

        return GarcesCodeCatalog::updateOrCreate(
            ['tipo' => $tipo, 'codigo_interno' => $codigoInterno],
            [
                'nombre' => $nombre,
                'notas' => $notas,
                'activo' => true,
                'updated_by' => $userId,
                'created_by' => $userId,
            ]
        );
    }

    /**
     * @return Collection<int,GarcesCodeCatalog>
     */
    public function allForTipo(string $tipo, bool $soloActivos = true): Collection
    {
        return GarcesCodeCatalog::query()
            ->tipo($tipo)
            ->when($soloActivos, fn ($q) => $q->activo())
            ->orderBy('codigo_interno')
            ->get();
    }

    /**
     * Importa filas tipo/código/nombre de forma idempotente.
     *
     * @param  array<int,array{tipo?:string,codigo_interno?:string,codigo?:string,nombre?:string|null,notas?:string|null}>  $rows
     * @return array{created:int,updated:int,skipped:int}
     */
    public function import(array $rows, ?int $userId = null): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $tipo = trim((string) ($row['tipo'] ?? ''));

            $codigo = trim((string) ($row['codigo_interno'] ?? $row['codigo'] ?? ''));

            if ($tipo === '' || $codigo === '') {
                $skipped++;

                continue;
            }

            $catalog = GarcesCodeCatalog::query()
                ->where('tipo', $tipo)
                ->where('codigo_interno', $codigo)
                ->first();

            if ($catalog) {
                $catalog->fill([
                    'nombre' => $row['nombre'] ?? $catalog->nombre,
                    'notas' => $row['notas'] ?? $catalog->notas,
                    'activo' => true,
                    'updated_by' => $userId,
                ])->save();

                $updated++;

                continue;
            }

            GarcesCodeCatalog::create([
                'tipo' => $tipo,
                'codigo_interno' => $codigo,
                'nombre' => $row['nombre'] ?? null,
                'notas' => $row['notas'] ?? null,
                'activo' => true,
                'created_by' => $userId,
            ]);

            $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    public function delete(GarcesCodeCatalog $catalog): void
    {
        $catalog->delete();
    }
}
