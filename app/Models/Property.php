<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use App\Services\AddressNormalizationService;
use App\Services\PropertyMatchingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'address',
        'city',
        'state',
        'zip_code',
        'property_type',
        'transaction_type',
        'district',
        'plan_number',
        'area_sqm',
        'facing',
        'street_width_m',
        'property_age_years',
        'floors',
        'units',
        'furnished',
        'finance_eligible',
        'price_per_sqm',
        'latitude',
        'longitude',
        'bedrooms',
        'bathrooms',
        'square_footage',
        'year_built',
        'lot_size',
        'estimated_value',
        'repair_estimate',
        'after_repair_value',
        'asking_price',
        'our_offer',
        'maximum_allowable_offer',
        'condition',
        'distress_markers',
        'list_price',
        'listing_status',
        'listed_at',
        'sold_at',
        'sold_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'area_sqm' => 'decimal:2',
            'street_width_m' => 'decimal:2',
            'furnished' => 'boolean',
            'finance_eligible' => 'boolean',
            'price_per_sqm' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'estimated_value' => 'decimal:2',
            'repair_estimate' => 'decimal:2',
            'after_repair_value' => 'decimal:2',
            'asking_price' => 'decimal:2',
            'our_offer' => 'decimal:2',
            'maximum_allowable_offer' => 'decimal:2',
            'distress_markers' => 'array',
            'list_price' => 'decimal:2',
            'sold_price' => 'decimal:2',
            'listed_at' => 'date',
            'sold_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::saving(function (Property $property) {
            if ($property->isDirty('address') && $property->address) {
                $property->address = AddressNormalizationService::normalize($property->address);
            }
            if ($property->isDirty('city') && $property->city) {
                $property->city = AddressNormalizationService::normalizeCity($property->city);
            }
            if ($property->isDirty('state') && $property->state) {
                $property->state = AddressNormalizationService::normalizeState($property->state);
            }
            if ($property->isDirty('zip_code') && $property->zip_code) {
                $property->zip_code = AddressNormalizationService::normalizeZipCode($property->zip_code);
            }

            if ($property->isDirty(['area_sqm', 'list_price', 'asking_price'])) {
                $price = $property->list_price ?? $property->asking_price;
                $area = (float) ($property->area_sqm ?? 0);

                $property->price_per_sqm = $price !== null && $area > 0
                    ? round((float) $price / $area, 2)
                    : null;
            }
        });

        static::deleting(function (Property $property) {
            $paths = $property->photos()
                ->withoutGlobalScopes()
                ->pluck('path')
                ->filter()
                ->all();

            if ($paths !== []) {
                Storage::disk('public')->delete($paths);
            }
        });

        static::saved(function (Property $property) {
            $matchingFields = [
                'transaction_type','property_type','city','district','area_sqm','list_price',
                'asking_price','bedrooms','street_width_m','facing','property_age_years',
                'floors','units','furnished','finance_eligible','listing_status',
            ];

            if ($property->wasRecentlyCreated || $property->wasChanged($matchingFields)) {
                app(PropertyMatchingService::class)->refreshForProperty($property);
            }
        });
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function comparableSales()
    {
        return $this->hasMany(ComparableSale::class)->latest('sale_date');
    }

    public function photos()
    {
        return $this->hasMany(PropertyPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function matches()
    {
        return $this->hasMany(PropertyMatch::class);
    }

    public function showings()
    {
        return $this->hasMany(Showing::class);
    }

    public function deals()
    {
        return $this->hasMany(Deal::class);
    }

    public function openHouses()
    {
        return $this->hasMany(OpenHouse::class);
    }

    public function getFullAddressAttribute(): string
    {
        return implode('، ', array_values(array_filter([
            $this->address,
            $this->district,
            $this->city,
            $this->state,
            $this->zip_code,
        ], fn ($part) => $part !== null && trim((string) $part) !== '')));
    }

    public function getAssignmentFeeAttribute(): ?float
    {
        if ($this->our_offer && $this->after_repair_value && $this->repair_estimate) {
            return $this->our_offer - ($this->after_repair_value * 0.70) - $this->repair_estimate;
        }
        return null;
    }

    public function getMaoAttribute(): ?float
    {
        if ($this->after_repair_value && $this->repair_estimate) {
            return ($this->after_repair_value * 0.70) - $this->repair_estimate;
        }
        return null;
    }
}
