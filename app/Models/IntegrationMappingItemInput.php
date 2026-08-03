<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationMappingItemInput extends Model
{
    public $timestamps = false;

    protected $fillable = ['mapping_item_id', 'clave', 'valor_entrada'];

    public function mappingItem(): BelongsTo
    {
        return $this->belongsTo(IntegrationMappingItem::class, 'mapping_item_id');
    }
}
