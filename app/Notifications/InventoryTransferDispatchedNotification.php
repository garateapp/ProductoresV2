<?php

namespace App\Notifications;

use App\Models\InventoryMovement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InventoryTransferDispatchedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InventoryMovement $movement,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->movement->loadMissing(['origin:id,nombre,codigo', 'destination:id,nombre,codigo', 'transferUnits.logisticUnit:id,license_plate_number']);

        return [
            'kind' => 'inventory_transfer_dispatched',
            'movement_id' => $this->movement->id,
            'folio' => $this->movement->folio,
            'origin' => $this->movement->origin ? [
                'id' => $this->movement->origin->id,
                'codigo' => $this->movement->origin->codigo,
                'nombre' => $this->movement->origin->nombre,
            ] : null,
            'destination' => $this->movement->destination ? [
                'id' => $this->movement->destination->id,
                'codigo' => $this->movement->destination->codigo,
                'nombre' => $this->movement->destination->nombre,
            ] : null,
            'transfer_units' => $this->movement->transferUnits->map(fn ($unit) => [
                'id' => $unit->id,
                'logistic_unit_id' => $unit->logistic_unit_id,
                'license_plate_number' => $unit->logisticUnit?->license_plate_number,
                'quantity' => (float) $unit->quantity,
                'status' => $unit->status,
            ])->values()->all(),
        ];
    }
}
