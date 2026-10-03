@extends('layouts.app')

@section('title', 'المطابقات')
@section('page-title', 'المطابقات')

@section('content')
@php
    $reasonLabels = [
        'transaction_type' => 'العملية',
        'property_type' => 'النوع',
        'city' => 'المدينة',
        'district' => 'الحي',
        'price' => 'السعر',
        'area' => 'المساحة',
        'bedrooms' => 'الغرف',
        'street_width' => 'عرض الشارع',
        'frontage' => 'الواجهة',
        'age' => 'العمر',
        'finance' => 'التمويل',
    ];
@endphp
<div dir="rtl" class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">أفضل المطابقات</h3>
            <div class="text-muted small mt-1">العقارات التي اجتازت القيود الأساسية مرتبة حسب درجة المطابقة.</div>
        </div>
    </div>

    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">أقل نسبة</label>
                <input type="number" min="0" max="100" name="min_score" class="form-control form-control-sm" value="{{ request('min_score', 70) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">العملية</label>
                <select name="transaction_type" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    @foreach($transactionTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('transaction_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">نوع العقار</label>
                <select name="property_type" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    @foreach($propertyTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('property_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">المدينة</label>
                <input name="city" class="form-control form-control-sm" value="{{ request('city') }}" placeholder="مثال: الرياض">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">تصفية</button>
                <a href="{{ route('property-matches.index') }}" class="btn btn-sm btn-outline-secondary">مسح</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>النسبة</th>
                    <th>العميل والطلب</th>
                    <th>العقار</th>
                    <th>السعر</th>
                    <th>لماذا تطابق؟</th>
                    <th>الوسيط</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($matches as $match)
                @php
                    $property = $match->property;
                    $searchRequest = $match->request;
                @endphp
                <tr>
                    <td>
                        <span class="badge bg-{{ $match->match_score >= 85 ? 'green' : ($match->match_score >= 70 ? 'blue' : 'yellow') }} fs-4">
                            {{ $match->match_score }}%
                        </span>
                    </td>
                    <td>
                        @if($searchRequest->lead)
                            <strong>{{ $searchRequest->lead->full_name }}</strong>
                        @else
                            <strong>طلب #{{ $searchRequest->id }}</strong>
                        @endif
                        <div class="text-muted small">
                            {{ $transactionTypes[$searchRequest->transaction_type] ?? $searchRequest->transaction_type }}
                            · {{ $propertyTypes[$searchRequest->property_type] ?? $searchRequest->property_type }}
                        </div>
                        <a class="small" href="{{ route('property-requests.show', $searchRequest) }}">فتح الطلب</a>
                    </td>
                    <td>
                        <strong>{{ $property->address }}</strong>
                        <div class="text-muted small">{{ $property->district ? $property->district.'، ' : '' }}{{ $property->city }}</div>
                        @if($property->area_sqm)
                            <div class="text-muted small">{{ number_format((float) $property->area_sqm) }} م²</div>
                        @endif
                    </td>
                    <td>{{ number_format((float) ($property->list_price ?? $property->asking_price ?? 0)) }} ر.س</td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($match->match_reasons ?? [] as $reason)
                                <span class="badge bg-green-lt text-green">✓ {{ $reasonLabels[$reason] ?? $reason }}</span>
                            @endforeach
                        </div>
                        @if(!empty($match->rejection_reasons))
                            <div class="text-warning small mt-1">⚠ توجد تفضيلات غير متحققة</div>
                        @endif
                    </td>
                    <td>{{ $searchRequest->agent?->name ?? '—' }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('properties.show', $property) }}">العقار</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">لا توجد مطابقات وفق المرشحات الحالية.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($matches->hasPages())
        <div class="card-footer">{{ $matches->links() }}</div>
    @endif
</div>
@endsection
