<?php

namespace App\Services\Inventory;

use App\Models\InventoryNumberSequence;
use Illuminate\Database\QueryException;
use RuntimeException;

class ReferenceNumberService
{
    public const PERSON_DELIVERY_KEY = 'inventory.person_delivery.referencia';

    /**
     * Reserva el siguiente correlativo de la secuencia indicada.
     *
     * Debe invocarse dentro de una transacción: el bloqueo de la fila de secuencia
     * serializa a las solicitudes concurrentes y el incremento se revierte junto
     * con el resto de la operación si algo falla.
     */
    public function next(string $key): int
    {
        $sequence = $this->lockSequence($key);
        $sequence->increment('value');

        return (int) $sequence->fresh()->value;
    }

    private function lockSequence(string $key): InventoryNumberSequence
    {
        $sequence = InventoryNumberSequence::query()
            ->where('key', $key)
            ->lockForUpdate()
            ->first();

        if ($sequence) {
            return $sequence;
        }

        try {
            return InventoryNumberSequence::query()->create([
                'key' => $key,
                'value' => 0,
            ]);
        } catch (QueryException $exception) {
            // Otra transacción insertó la fila entre el SELECT y el INSERT.
            $sequence = InventoryNumberSequence::query()
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                throw new RuntimeException(
                    "No fue posible inicializar la secuencia de referencia [{$key}].",
                    previous: $exception,
                );
            }

            return $sequence;
        }
    }
}
