<?php

namespace App\Notifications;

use App\Models\InventoryMovement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InventoryTransferReturnPendingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InventoryMovement $movement,
        public readonly array $transferUnits,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->movement->loadMissing(['origin:id,nombre,codigo', 'destination:id,nombre,codigo']);

        return [
            'kind' => 'inventory_transfer_return_pending',
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
            'transfer_units' => $this->transferUnits,
        ];
    }
}
