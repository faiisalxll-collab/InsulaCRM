<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Showing extends Model
{
    public const STATUSES = [
        'scheduled' => 'مجدولة',
        'completed' => 'مكتملة',
        'cancelled' => 'ملغاة',
        'no_show'   => 'لم يحضر',
    ];

    public const OUTCOMES = [
        'interested'            => 'مهتم',
        'not_interested'        => 'غير مهتم',
        'made_offer'            => 'قدم عرضًا',
        'needs_second_showing'  => 'يحتاج معاينة ثانية',
    ];

    protected $fillable = [
        'tenant_id',
        'deal_id',
        'property_request_id',
        'property_id',
        'lead_id',
        'agent_id',
        'showing_date',
        'showing_time',
        'duration_minutes',
        'status',
        'feedback',
        'outcome',
        'listing_agent_name',
        'listing_agent_phone',
        'notes',
    ];

    protected $casts = [
        'showing_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function propertyRequest()
    {
        return $this->belongsTo(PropertyRequest::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public static function statusLabel(string $status): string
    {
        return __(self::STATUSES[$status] ?? ucwords(str_replace('_', ' ', $status)));
    }

    public static function outcomeLabel(string $outcome): string
    {
        return __(self::OUTCOMES[$outcome] ?? ucwords(str_replace('_', ' ', $outcome)));
    }
}
