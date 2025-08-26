<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $fillable = [
        'user_id',
        'contract_file_path',
        'fecha_contrato',
        'vencimiento',
        'comision',
        'flete_a_huerto',
        'rebate',
        'bonificacion',
        'tarifa_premium',
        'comparativa',
        'descuento_fruta_comercial',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
