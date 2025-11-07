<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialDiscardDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'commercial_discard_id',
        'parametro_id',
        'valor_id',
        'comercial',
        'desecho',
    ];

    public function parametro()
    {
        return $this->belongsTo(Parametro::class);
    }

    public function valor()
    {
        return $this->belongsTo(Valor::class);
    }

    public function discard()
    {
        return $this->belongsTo(CommercialDiscard::class, 'commercial_discard_id');
    }
}
