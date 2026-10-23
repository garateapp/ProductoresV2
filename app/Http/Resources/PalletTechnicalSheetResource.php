<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PalletTechnicalSheetResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly string $folio,
        private readonly string $cEmbalaje,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'folio' => $this->folio,
            'c_embalaje' => $this->cEmbalaje,
            'technical_sheet' => [
                'id' => $this->id,
                'nombre' => $this->nombre,
                'version' => $this->version,
                'fecha_vigencia_desde' => optional($this->fecha_vigencia_desde)->toDateString(),
                'fecha_vigencia_hasta' => optional($this->fecha_vigencia_hasta)->toDateString(),
                'packaging' => [
                    'id' => $this->packaging?->id,
                    'codigo' => $this->packaging?->codigo,
                    'nombre' => $this->packaging?->nombre,
                ],
                'items' => $this->palletItems->map(fn ($item) => [
                    'id' => $item->id,
                    'material' => [
                        'id' => $item->material?->id,
                        'codigo' => $item->material?->codigo,
                        'nombre' => $item->material?->nombre,
                    ],
                    'replacement_material' => $item->replacementMaterial ? [
                        'id' => $item->replacementMaterial->id,
                        'codigo' => $item->replacementMaterial->codigo,
                        'nombre' => $item->replacementMaterial->nombre,
                    ] : null,
                    'cantidad_estandar' => (float) $item->cantidad_estandar,
                    'calibre' => $item->calibre,
                ])->values()->all(),
            ],
        ];
    }
}
