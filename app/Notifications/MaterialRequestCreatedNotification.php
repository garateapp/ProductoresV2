<?php

namespace App\Notifications;

use App\Models\InventoryMaterialRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaterialRequestCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly InventoryMaterialRequest $materialRequest,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->materialRequest->loadMissing(['originLocation:id,nombre', 'destinationLocation:id,nombre', 'creator:id,name']);

        return [
            'kind' => 'material_request_created',
            'material_request_id' => $this->materialRequest->id,
            'codigo' => $this->materialRequest->codigo,
            'creator_name' => $this->materialRequest->creator?->name,
            'origin' => $this->materialRequest->originLocation ? [
                'id' => $this->materialRequest->originLocation->id,
                'nombre' => $this->materialRequest->originLocation->nombre,
            ] : null,
            'destination' => $this->materialRequest->destinationLocation ? [
                'id' => $this->materialRequest->destinationLocation->id,
                'nombre' => $this->materialRequest->destinationLocation->nombre,
            ] : null,
        ];
    }
}
