<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProducerCertification extends Model
{
    use HasFactory;

    protected $fillable = [
        'certifying_house_id',
        'name',
        'certificate_type_id',
        'certificate_code',
        'especie_id',
        'audit_date',
        'expiration_date',
        'certificate_pdf_path',
        'has_pdf_extension',
        'user_id',
    ];

    protected $casts = [
        'audit_date' => 'date',
        'expiration_date' => 'date',
        'has_pdf_extension' => 'boolean',
    ];

    public function certifyingHouse()
    {
        return $this->belongsTo(CertifyingHouse::class);
    }

    public function certificateType()
    {
        return $this->belongsTo(CertificateType::class);
    }

    public function especie()
    {
        return $this->belongsTo(Especie::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function otherDocuments()
    {
        return $this->hasMany(CertificationOtherDocument::class, 'certification_id');
    }
}

