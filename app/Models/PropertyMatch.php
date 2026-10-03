<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PropertyMatch extends Model
{
    protected $fillable = [
        'tenant_id','property_request_id','property_id','match_score',
        'hard_constraints_passed','location_score','price_score','area_score',
        'features_score','finance_score','match_reasons','rejection_reasons','status','matched_at','evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'hard_constraints_passed' => 'boolean',
            'match_reasons' => 'array',
            'rejection_reasons' => 'array',
            'matched_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function request() { return $this->belongsTo(PropertyRequest::class, 'property_request_id'); }
    public function property() { return $this->belongsTo(Property::class); }
}
