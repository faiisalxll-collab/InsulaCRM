@extends('layouts.app')

@section('title', 'تفاصيل الطلب')
@section('page-title', 'تفاصيل الطلب')

@section('content')
@php
    $reasonLabels = [
        'transaction_type' => 'نوع العملية',
        'property_type' => 'نوع العقار',
        'city' => 'المدينة',
        'district' => 'الحي',
        'price' => 'السعر',
        'area' => 'المساحة',
        'bedrooms' => 'الغرف',
        'street_width' => 'عرض الشارع',
        'facing' => 'اتجاه الواجهة',
        'age' => 'عمر العقار',
        'finance' => 'التمويل',
    ];
    $warningLabels = [
        'bedrooms_preference_not_met' => 'عدد الغرف أقل من المفضل',
        'street_width_preference_not_met' => 'عرض الشارع أقل من المفضل',
        'facing_preference_not_met' => 'اتجاه الواجهة المفضل غير متحقق',
        'age_preference_not_met' => 'عمر العقار أكبر من المفضل',
    ];
@endphp
<div dir="rtl">
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">الطلب #{{ $propertyRequest->id }}</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">العملية</dt>
                        <dd class="col-7">{{ $transactionTypes[$propertyRequest->transaction_type] ?? $propertyRequest->transaction_type }}</dd>
                        <dt class="col-5">نوع العقار</dt>
                        <dd class="col-7">{{ $propertyTypes[$propertyRequest->property_type] ?? $propertyRequest->property_type }}</dd>
                        <dt class="col-5">المدينة</dt>
                        <dd class="col-7">{{ $propertyRequest->city }}</dd>
                        <dt class="col-5">الأحياء</dt>
                        <dd class="col-7">{{ !empty($propertyRequest->districts) ? implode('، ', $propertyRequest->districts) : 'أي حي' }}</dd>
                        <dt class="col-5">الميزانية</dt>
                        <dd class="col-7">
                            {{ $propertyRequest->min_price ? number_format((float) $propertyRequest->min_price) : '0' }}
                            –
                            {{ $propertyRequest->max_price ? number_format((float) $propertyRequest->max_price) : 'مفتوح' }} ر.س
                        </dd>
                        <dt class="col-5">المساحة الدنيا</dt>
                        <dd class="col-7">{{ $propertyRequest->min_area_sqm ? number_format((float) $propertyRequest->min_area_sqm) . ' م²' : '—' }}</dd>
                        <dt class="col-5">العميل</dt>
                        <dd class="col-7">
                            @if($propertyRequest->lead)
                                <a href="{{ route('leads.show', $propertyRequest->lead) }}">{{ $propertyRequest->lead->full_name }}</a>
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-5">الوسيط</dt>
                        <dd class="col-7">{{ $propertyRequest->agent?->name ?? '—' }}</dd>
                        <dt class="col-5">الحالة</dt>
                        <dd class="col-7">{{ $statuses[$propertyRequest->status] ?? $propertyRequest->status }}</dd>
                    </dl>
                    @if($propertyRequest->notes)
                        <hr><div class="text-muted small">ملاحظات</div><div>{{ $propertyRequest->notes }}</div>
                    @endif
                </div>
                <div class="card-footer d-flex flex-wrap gap-2">
                    <a class="btn btn-primary" href="{{ route('property-requests.edit', $propertyRequest) }}">تعديل</a>
                    <form method="POST" action="{{ route('property-requests.refresh', $propertyRequest) }}">
                        @csrf
                        <button class="btn btn-outline-primary">إعادة المطابقة</button>
                    </form>
                    @if(($propertyRequest->showings_count ?? 0) === 0 && ($propertyRequest->deals_count ?? 0) === 0)
                    <form method="POST" action="{{ route('property-requests.destroy', $propertyRequest) }}" onsubmit="return confirm('حذف الطلب نهائيًا؟')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger">حذف</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">العقارات المطابقة</h3>
                        <div class="text-muted small mt-1">القيود الأساسية يجب أن تنجح أولًا، ثم تُحسب درجة المطابقة من 100.</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>النسبة</th>
                                <th>العقار</th>
                                <th>السعر</th>
                                <th>أسباب المطابقة</th>
                                <th>ملاحظات</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($matches as $match)
                            @php $property = $match->property; @endphp
                            <tr>
                                <td>
                                    <span class="badge bg-{{ $match->match_score >= 85 ? 'green' : ($match->match_score >= 70 ? 'blue' : 'yellow') }} fs-4">
                                        {{ $match->match_score }}%
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $property->address }}</strong>
                                    <div class="text-muted small">{{ $property->district ? $property->district.'، ' : '' }}{{ $property->city }}</div>
                                    <div class="text-muted small">{{ $propertyTypes[$property->property_type] ?? $property->property_type }} · {{ $property->area_sqm ? number_format((float) $property->area_sqm).' م²' : 'مساحة غير محددة' }}</div>
                                </td>
                                <td>{{ number_format((float) ($property->list_price ?? $property->asking_price ?? 0)) }} ر.س</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($match->match_reasons ?? [] as $reason)
                                            <span class="badge bg-green-lt text-green">✓ {{ $reasonLabels[$reason] ?? $reason }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    @forelse($match->rejection_reasons ?? [] as $warning)
                                        <div class="small text-warning">⚠ {{ $warningLabels[$warning] ?? $warning }}</div>
                                    @empty
                                        <span class="text-muted">لا توجد</span>
                                    @endforelse
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a class="btn btn-sm btn-primary"
                                           href="{{ route('showings.create', ['property_request_id' => $propertyRequest->id, 'property_id' => $property->id]) }}">
                                            جدولة معاينة
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('properties.show', $property) }}">العقار</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">لا توجد مطابقة نشطة حاليًا.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($matches->hasPages())
                    <div class="card-footer">{{ $matches->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
