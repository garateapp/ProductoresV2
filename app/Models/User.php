<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rut',
        'user',
        'idprod',
        'csg',
        'current_team_id',
        'profile_photo_path',
        'emnotification',
        'kilos_netos',
        'comercial',
        'desecho',
        'merma',
        'exp',
        'predio',
        'comuna',
        'provincia',
        'direccion',
        'antiguedad',
        'fitosanitario',
        'certificaciones',
        'status',
        'enviomasivo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function telefonos()
    {
        return $this->hasMany(Telefono::class);
    }

    public function agronomists()
    {
        return $this->belongsToMany(User::class, 'campo_staff', 'user_id', 'agronomo_id', 'id', 'id');
    }

    public function producers()
    {
        return $this->belongsToMany(User::class, 'campo_staff', 'agronomo_id', 'user_id', 'id', 'id');
    }

    public function especies()
    {
        return $this->belongsToMany(Especie::class, 'especie_user', 'user_id', 'especie_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class);
    }

    public function certifications()
    {
        return $this->hasMany(ProducerCertification::class);
    }

    public function certificateTypes()
    {
        return $this->belongsToMany(CertificateType::class);
    }

    public function csgEspecieCountryStatuses()
    {
        return $this->hasMany(CsgEspecieCountryStatus::class, 'user_id');
    }
}
