<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialDiscard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fecha',
        'linea',
        'turno',
        'productor',
        'especie',
        'variedad',
        'lote',
        'proceso',
        'frutos',
        'observaciones',
        'signature_path',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(CommercialDiscardDetail::class);
    }
}
