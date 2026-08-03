<?php

namespace App\Notifications;

use App\Models\InventoryReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReturnCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InventoryReturn $return,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->return->loadMissing([
            'originLocation:id,nombre',
            'destinationLocation:id,nombre',
            'creator:id,name',
        ]);

        return [
            'kind' => 'return_created',
            'return_id' => $this->return->id,
            'codigo' => $this->return->codigo,
            'creator_name' => $this->return->creator?->name,
            'origin' => $this->return->originLocation ? [
                'id' => $this->return->originLocation->id,
                'nombre' => $this->return->originLocation->nombre,
            ] : null,
            'destination' => $this->return->destinationLocation ? [
                'id' => $this->return->destinationLocation->id,
                'nombre' => $this->return->destinationLocation->nombre,
            ] : null,
        ];
    }
}
