<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EstimationVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'season_id' => $this->season_id,
            'season' => $this->whenLoaded('season', function () {
                return [
                    'id' => $this->season->id,
                    'code' => $this->season->code,
                    'name' => $this->season->name,
                ];
            }),
            'type' => $this->type?->value ?? $this->type,
            'period_start_week' => $this->period_start_week,
            'period_end_week' => $this->period_end_week,
            'source' => $this->source,
            'status' => $this->status?->value ?? $this->status,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'notes' => $this->notes,
            'uploaded_by' => $this->uploaded_by,
            'uploader' => $this->whenLoaded('uploader', function () {
                return [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                ];
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'rows_count' => $this->whenCounted('rows'),
        ];
    }
}