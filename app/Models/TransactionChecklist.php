<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class TransactionChecklist extends Model
{
    protected $table = 'transaction_checklists';

    public const STATUSES = [
        'pending' => 'معلقة',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتملة',
        'waived' => 'غير مطلوبة',
        'failed' => 'متعذرة',
    ];

    public const DEFAULT_ITEMS = [
        ['item_key' => 'party_verification', 'label' => 'التحقق من بيانات الأطراف', 'sort_order' => 1],
        ['item_key' => 'ownership_verification', 'label' => 'التحقق من بيانات العقار والملكية', 'sort_order' => 2],
        ['item_key' => 'agreement_review', 'label' => 'مراجعة الاتفاق', 'sort_order' => 3],
        ['item_key' => 'financing', 'label' => 'استكمال التمويل إن وجد', 'sort_order' => 4],
        ['item_key' => 'property_inspection', 'label' => 'فحص العقار إن لزم', 'sort_order' => 5],
        ['item_key' => 'payment', 'label' => 'استكمال الدفعات أو العربون', 'sort_order' => 6],
        ['item_key' => 'handover', 'label' => 'التسليم', 'sort_order' => 7],
        ['item_key' => 'commission', 'label' => 'تسجيل العمولة', 'sort_order' => 8],
        ['item_key' => 'closing_documents', 'label' => 'حفظ مستندات الإغلاق', 'sort_order' => 9],
    ];

    protected $fillable = [
        'tenant_id',
        'deal_id',
        'item_key',
        'label',
        'status',
        'deadline',
        'completed_at',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->deadline) {
            return false;
        }

        return $this->deadline->isPast() && !in_array($this->status, ['completed', 'waived']);
    }
}
