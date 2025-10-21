<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QualityControlPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'calidad_id',
        'photo_type_id',
        'path',
        'observations',
    ];

    protected $appends = ['url', 'inline_url'];

    public function calidad()
    {
        return $this->belongsTo(Calidad::class);
    }

    public function photoType()
    {
        return $this->belongsTo(PhotoType::class);
    }

    public function getUrlAttribute()
    {
        return Storage::url($this->path);
    }

    public function getInlineUrlAttribute(): ?string
    {
        $path = $this->path;

        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['data:', 'http://', 'https://'])) {
            return $path;
        }

        $relative = Str::startsWith($path, 'public/') ? Str::after($path, 'public/') : ltrim($path, '/');

        try {
            if (Storage::disk('public')->exists($relative)) {
                $contents = Storage::disk('public')->get($relative);
                $mime = Storage::disk('public')->mimeType($relative) ?: 'image/jpeg';

                if (! empty($contents)) {
                    return 'data:' . $mime . ';base64,' . base64_encode($contents);
                }
            }
        } catch (\Throwable $e) {
            // Fall through to absolute path checks.
        }

        if (Str::startsWith($relative, 'storage/')) {
            $absolute = public_path($relative);
            if (is_file($absolute)) {
                $mime = mime_content_type($absolute) ?: 'image/jpeg';
                $contents = file_get_contents($absolute);

                if (! empty($contents)) {
                    return 'data:' . $mime . ';base64,' . base64_encode($contents);
                }
            }
        }

        $storageAbsolute = storage_path('app/public/' . ltrim($relative, '/'));
        if (is_file($storageAbsolute)) {
            $mime = mime_content_type($storageAbsolute) ?: 'image/jpeg';
            $contents = file_get_contents($storageAbsolute);

            if (! empty($contents)) {
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            }
        }

        $publicAbsolute = public_path('storage/' . ltrim($relative, '/'));
        if (is_file($publicAbsolute)) {
            $mime = mime_content_type($publicAbsolute) ?: 'image/jpeg';
            $contents = file_get_contents($publicAbsolute);

            if (! empty($contents)) {
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            }
        }

        return null;
    }
}
