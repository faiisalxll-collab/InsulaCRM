<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id','lead_id','agent_id','transaction_type','property_type','city',
        'districts','min_price','max_price','min_area_sqm','min_bedrooms',
        'min_street_width_m','preferred_facings','max_property_age_years',
        'finance_required','status','notes',
    ];

    protected function casts(): array
    {
        return [
            'districts' => 'array',
            'preferred_facings' => 'array',
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'min_area_sqm' => 'decimal:2',
            'min_street_width_m' => 'decimal:2',
            'finance_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function lead() { return $this->belongsTo(Lead::class); }
    public function agent() { return $this->belongsTo(User::class, 'agent_id'); }
    public function matches() { return $this->hasMany(PropertyMatch::class); }
}
