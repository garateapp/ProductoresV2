<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Reversión del movimiento duplicado: "Traslado generado desde solicitud SOL-000004".
        // Se identifica por el folio de la solicitud de material, no por ID, para que funcione en producción.
        $movement = DB::table('inventory_movements')
            ->join('inventory_material_requests', 'inventory_movements.material_request_id', '=', 'inventory_material_requests.id')
            ->where('inventory_material_requests.codigo', 'SOL-000004')
            ->where('inventory_movements.motivo', 'like', 'Traslado generado desde solicitud%')
            ->select('inventory_movements.*')
            ->first();

        if (! $movement) {
            return;
        }

        $movementId = (int) $movement->id;
        $originId = (int) $movement->origin_location_id;
        $destinationId = (int) $movement->destination_location_id;

        $movementMeta = (array) json_decode((string) $movement->metadata, true);

        // Ya fue revertido por una corrida anterior de esta misma migración.
        if (($movementMeta['reverted_by_migration'] ?? null) === '2026_09_15_000001') {
            return;
        }

        DB::transaction(function () use ($movementId, $originId, $destinationId, $movement, $movementMeta) {
            $details = DB::table('inventory_movement_details')
                ->where('movement_id', $movementId)
                ->get();

            // 1) Restaurar las posiciones de origen que descontó el movimiento.
            $recreatedPositionIds = [];
            foreach ($details as $detail) {
                $meta = (array) json_decode((string) $detail->metadata, true);
                if (empty($meta) || $detail->sentido !== 'salida') {
                    continue;
                }

                $originPositionId = $meta['position_id'] ?? null;
                $snapshotQty = (float) ($meta['position_quantity_snapshot'] ?? $detail->cantidad);
                $lotCode = $meta['position_lot_code_snapshot'] ?? null;
                $logisticUnitId = $meta['position_logistic_unit_snapshot']['id'] ?? null;

                if ($originPositionId) {
                    $position = DB::table('inventory_stock_positions')->find($originPositionId);
                    if ($position) {
                        // Restaurar la cantidad que había antes del movimiento duplicado.
                        DB::table('inventory_stock_positions')
                            ->where('id', $originPositionId)
                            ->update(['quantity' => $snapshotQty, 'lot_code' => $lotCode]);
                    } else {
                        // La posición fue consumida por completo (llego a 0 y se eliminó).
                        $recreatedPositionIds[] = DB::table('inventory_stock_positions')->insertGetId([
                            'material_id' => $detail->material_id,
                            'location_id' => $originId,
                            'logistic_unit_id' => $logisticUnitId,
                            'quantity' => $snapshotQty,
                            'lot_code' => $lotCode,
                            'status' => 'available',
                            'metadata' => $detail->metadata,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // Restaurar la unidad logística a su ubicación de origen y su cantidad disponible.
                if ($logisticUnitId) {
                    $lu = DB::table('inventory_logistic_units')->find($logisticUnitId);
                    if ($lu) {
                        $base = (float) $lu->base_quantity;
                        $restored = min($base, (float) $lu->available_quantity + (float) $detail->cantidad);
                        DB::table('inventory_logistic_units')
                            ->where('id', $logisticUnitId)
                            ->update([
                                'current_location_id' => $originId,
                                'available_quantity' => $restored,
                                'last_moved_at' => now(),
                            ]);
                    }
                }
            }

            // 2) Eliminar las posiciones fantasma que este movimiento creó en destino.
            //    Se identifican por la ventana de creación del movimiento (no por las de la confirmación de escaneos, posteriores).
            $phantom = DB::table('inventory_stock_positions')
                ->where('location_id', $destinationId)
                ->whereBetween('created_at', [
                    \Carbon\Carbon::parse($movement->created_at),
                    \Carbon\Carbon::parse($movement->created_at)->addSeconds(90),
                ])
                ->whereNotIn('id', $recreatedPositionIds)
                ->get();

            foreach ($phantom as $p) {
                DB::table('inventory_stock_positions')->where('id', $p->id)->delete();
            }

            // 3) Eliminar transfer_units y allocations del movimiento (el historial en ledger se conserva).
            $detailIds = $details->pluck('id');

            $allocationIds = DB::table('inventory_movement_allocations')
                ->whereIn('movement_detail_id', $detailIds)
                ->pluck('id');

            if ($allocationIds->isNotEmpty()) {
                DB::table('inventory_ledger_events')
                    ->whereIn('allocation_id', $allocationIds)
                    ->update(['allocation_id' => null]);
            }

            DB::table('inventory_movement_allocations')
                ->whereIn('movement_detail_id', $detailIds)
                ->delete();

            DB::table('inventory_transfer_units')
                ->where('movement_id', $movementId)
                ->delete();

            // 4) Marcar el movimiento como revertido e identificar hacia qué request apuntaba.
            DB::table('inventory_movements')
                ->where('id', $movementId)
                ->update([
                    'estado' => 'revertido',
                    'metadata' => json_encode([
                        ...$movementMeta,
                        'reverted_by_migration' => '2026_09_15_000001',
                        'revertido_at' => now()->toDateTimeString(),
                        'revertido_reason' => 'Duplicación de despacho: el movimiento repetía cantidades ya cubiertas por escaneos parciales previos',
                    ]),
                ]);

            // 5) Restablecer el estado de la solicitud.
            DB::table('inventory_material_requests')
                ->where('id', $movement->material_request_id)
                ->update(['estado' => 'aprobado']);
        });
    }

    public function down(): void
    {
        // No reversible automáticamente — es un fix de datos.
    }
};