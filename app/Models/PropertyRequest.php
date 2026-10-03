<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\PropertyMatchingService;
use Illuminate\Validation\ValidationException;

class PropertyRequest extends Model
{
    use HasFactory;

    public const TRANSACTION_TYPES = [
        'sale' => 'شراء',
        'rent' => 'إيجار',
    ];

    public const STATUSES = [
        'active' => 'نشط',
        'paused' => 'موقوف',
        'fulfilled' => 'مكتمل',
        'closed' => 'مغلق',
    ];

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

        static::saving(function (PropertyRequest $request) {
            if ($request->lead_id !== null && ! Lead::withoutGlobalScopes()
                ->whereKey($request->lead_id)
                ->where('tenant_id', $request->tenant_id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'lead_id' => 'The selected client does not belong to this tenant.',
                ]);
            }

            if ($request->agent_id !== null && ! User::query()
                ->whereKey($request->agent_id)
                ->where('tenant_id', $request->tenant_id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'agent_id' => 'The selected broker does not belong to this tenant.',
                ]);
            }
        });

        static::saved(function (PropertyRequest $request) {
            $matchingFields = [
                'transaction_type','property_type','city','districts','min_price','max_price',
                'min_area_sqm','min_bedrooms','min_street_width_m','preferred_facings',
                'max_property_age_years','finance_required','status',
            ];

            if ($request->wasRecentlyCreated || $request->wasChanged($matchingFields)) {
                app(PropertyMatchingService::class)->refreshForRequest($request);
            }
        });
    }

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function lead() { return $this->belongsTo(Lead::class); }
    public function agent() { return $this->belongsTo(User::class, 'agent_id'); }
    public function matches() { return $this->hasMany(PropertyMatch::class); }
    public function deals() { return $this->hasMany(Deal::class); }
    public function showings() { return $this->hasMany(Showing::class); }
}
