<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, HasApiTokens;

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
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    public function telefonos()
    {
        return $this->hasMany(Telefono::class);
    }

    public function agronomists()
    {
        return $this->belongsToMany(User::class, 'campo_staff', 'user_id', 'agronomo_id', 'id', 'id')
            ->withPivot(['rol', 'campo_rut'])
            ->withTimestamps();
    }

    public function producers()
    {
        return $this->belongsToMany(User::class, 'campo_staff', 'agronomo_id', 'user_id', 'id', 'id')
            ->withPivot(['rol', 'campo_rut'])
            ->withTimestamps();
    }

    public function especies()
    {
        return $this->belongsToMany(Especie::class, 'especie_user', 'user_id', 'especie_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class);
    }

    public function recepciones()
    {
        return $this->hasMany(Recepcion::class, 'id_emisor', 'idprod');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
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

    public function weeklyHarvestEstimates()
    {
        return $this->hasMany(WeeklyHarvestEstimate::class, 'user_id');
    }

    public function agronomistWeeklyHarvestEstimates()
    {
        return $this->hasMany(WeeklyHarvestEstimate::class, 'agronomist_id');
    }

    public function producerGroups()
    {
        return $this->belongsToMany(ProducerGroup::class, 'producer_group_user', 'user_id', 'producer_group_id');
    }

    public function loginEvents()
    {
        return $this->hasMany(LoginEvent::class);
    }

    public function inventoryLocations(): BelongsToMany
    {
        return $this->belongsToMany(InventoryLocation::class, 'inventory_location_user', 'user_id', 'inventory_location_id')
            ->withTimestamps();
    }
}
