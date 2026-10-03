<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PropertyPhoto extends Model
{
    protected $fillable = [
        'tenant_id',
        'property_id',
        'uploaded_by',
        'filename',
        'original_name',
        'path',
        'mime_type',
        'size',
        'caption',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (PropertyPhoto $photo) {
            if (empty($photo->tenant_id) && auth()->check()) {
                $photo->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
