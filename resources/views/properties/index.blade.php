@extends('layouts.app')

@section('title', ($businessMode ?? 'wholesale') === 'realestate' ? 'العقارات' : __('Properties'))
@section('page-title', ($businessMode ?? 'wholesale') === 'realestate' ? 'العقارات' : __('Properties'))

@section('content')
@php
    $isRealEstate = ($businessMode ?? 'wholesale') === 'realestate';
    $transactionLabels = ['sale' => 'بيع', 'rent' => 'إيجار'];
    $statusLabels = [
        'active' => 'نشط',
        'pending' => 'معلّق',
        'sold' => 'مباع',
        'withdrawn' => 'مسحوب',
        'expired' => 'منتهي',
    ];
    $statusColors = [
        'active' => 'bg-green-lt',
        'pending' => 'bg-yellow-lt',
        'sold' => 'bg-blue-lt',
        'withdrawn' => 'bg-secondary-lt',
        'expired' => 'bg-red-lt',
    ];
@endphp

<div class="card" @if($isRealEstate) dir="rtl" @endif>
    <div class="card-header">
        <div>
            <h3 class="card-title">{{ $isRealEstate ? 'جميع العقارات' : __('All Properties') }}</h3>
            @if($isRealEstate)
                <div class="text-muted small mt-1">العقارات المعروضة للبيع والإيجار مع بياناتها ومطابقاتها النشطة.</div>
            @endif
        </div>
        @if($isRealEstate)
        <div class="card-actions">
            <a href="{{ route('properties.create') }}" class="btn btn-primary">إضافة عقار</a>
        </div>
        @endif
    </div>

    <div class="card-body border-bottom py-3">
        <form method="GET" action="{{ route('properties.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="property-search" class="form-label">{{ $isRealEstate ? 'بحث' : __('Search') }}</label>
                <input type="text" id="property-search" name="search" class="form-control"
                       placeholder="{{ $isRealEstate ? 'العنوان، المدينة، الحي، المخطط...' : __('Search address, city, zip...') }}"
                       value="{{ request('search') }}">
            </div>

            <div class="col-md-2">
                <label for="property-type-filter" class="form-label">{{ $isRealEstate ? 'نوع العقار' : __('Property type') }}</label>
                <select id="property-type-filter" name="property_type" class="form-select">
                    <option value="">{{ $isRealEstate ? 'كل الأنواع' : __('All Types') }}</option>
                    @foreach($propertyTypes as $val => $label)
                        <option value="{{ $val }}" @selected(request('property_type') == $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if($isRealEstate)
                <div class="col-md-2">
                    <label class="form-label">نوع العملية</label>
                    <select name="transaction_type" class="form-select">
                        <option value="">بيع وإيجار</option>
                        @foreach($transactionLabels as $val => $label)
                            <option value="{{ $val }}" @selected(request('transaction_type') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">الحي</label>
                    <input type="text" name="district" class="form-control" value="{{ request('district') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">حالة العرض</label>
                    <select name="listing_status" class="form-select">
                        <option value="">كل الحالات</option>
                        @foreach($statusLabels as $val => $label)
                            <option value="{{ $val }}" @selected(request('listing_status') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="col-md-2">
                    <label for="property-condition-filter" class="form-label">{{ __('Property condition') }}</label>
                    <select id="property-condition-filter" name="condition" class="form-select">
                        <option value="">{{ __('All Conditions') }}</option>
                        @foreach(App\Services\CustomFieldService::getOptions('property_condition') as $val => $label)
                            <option value="{{ $val }}" @selected(request('condition') == $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="property-distress-filter" class="form-label">{{ __('Distress Markers') }}</label>
                    <select id="property-distress-filter" name="distress[]" class="form-select" multiple size="1">
                        @foreach(App\Services\CustomFieldService::getOptions('distress_markers') as $val => $label)
                            <option value="{{ $val }}" @selected(is_array(request('distress')) && in_array($val, request('distress')))>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ $isRealEstate ? 'تصفية' : __('Filter') }}</button>
            </div>
            @if(request()->hasAny(['search', 'property_type', 'condition', 'distress', 'listing_status', 'transaction_type', 'district']))
                <div class="col-auto">
                    <a href="{{ route('properties.index') }}" class="btn btn-outline-secondary">{{ $isRealEstate ? 'مسح' : __('Clear') }}</a>
                </div>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>{{ $isRealEstate ? 'العقار' : __('Address') }}</th>
                    <th>{{ $isRealEstate ? 'العميل/المالك' : __('Lead') }}</th>
                    <th>{{ $isRealEstate ? 'النوع' : __('Type') }}</th>
                    @if(!$isRealEstate)
                        <th>{{ __('Est. Value') }}</th>
                        <th>{{ __('Repair Est.') }}</th>
                        <th>{{ __('ARV') }}</th>
                        <th>{{ __('Our Offer') }}</th>
                        <th>{{ __('Assignment Fee') }}</th>
                        <th>{{ __('Distress Markers') }}</th>
                    @else
                        <th>العملية</th>
                        <th>السعر</th>
                        <th>الحالة</th>
                        <th>المساحة</th>
                        <th>الغرف</th>
                        <th>التمويل</th>
                        <th>المطابقات</th>
                    @endif
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($properties as $property)
                <tr>
                    <td>
                        <strong>{{ $property->address }}</strong>
                        <div class="text-muted small">
                            {{ $property->district ? $property->district.'، ' : '' }}{{ $property->city }}
                        </div>
                    </td>
                    <td>
                        @if($property->lead)
                            <a href="{{ route('leads.show', $property->lead) }}">{{ $property->lead->full_name }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $propertyTypes[$property->property_type] ?? ucwords(str_replace('_', ' ', $property->property_type)) }}</td>

                    @if(!$isRealEstate)
                        <td>{{ Fmt::currency($property->estimated_value) }}</td>
                        <td>{{ Fmt::currency($property->repair_estimate) }}</td>
                        <td>{{ Fmt::currency($property->after_repair_value) }}</td>
                        <td>{{ Fmt::currency($property->our_offer) }}</td>
                        <td>
                            @if($property->assignment_fee !== null)
                                <span class="{{ $property->assignment_fee >= 0 ? 'text-green' : 'text-red' }}">{{ Fmt::currency($property->assignment_fee) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @forelse($property->distress_markers ?? [] as $marker)
                                <span class="badge bg-red-lt me-1 mb-1">{{ __(ucwords(str_replace('_', ' ', $marker))) }}</span>
                            @empty
                                <span class="text-secondary">—</span>
                            @endforelse
                        </td>
                    @else
                        <td><span class="badge bg-blue-lt">{{ $transactionLabels[$property->transaction_type] ?? $property->transaction_type }}</span></td>
                        <td>
                            <strong>{{ Fmt::currency($property->list_price ?? $property->asking_price) }}</strong>
                            @if($property->price_per_sqm)
                                <div class="text-muted small">{{ number_format((float) $property->price_per_sqm) }} ر.س/م²</div>
                            @endif
                        </td>
                        <td>
                            @if($property->listing_status)
                                <span class="badge {{ $statusColors[$property->listing_status] ?? 'bg-secondary-lt' }}">
                                    {{ $statusLabels[$property->listing_status] ?? $property->listing_status }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $property->area_sqm ? number_format((float) $property->area_sqm) . ' م²' : '—' }}</td>
                        <td>{{ $property->bedrooms ?? '—' }}</td>
                        <td>
                            @if($property->finance_eligible === true)
                                <span class="badge bg-green-lt">يقبل</span>
                            @elseif($property->finance_eligible === false)
                                <span class="badge bg-secondary-lt">لا</span>
                            @else
                                —
                            @endif
                        </td>
                        <td><span class="badge bg-blue">{{ $property->eligible_matches_count }}</span></td>
                    @endif

                    <td>
                        <a href="{{ route('properties.show', $property) }}" class="btn btn-sm btn-outline-primary">
                            {{ $isRealEstate ? 'فتح' : __('View') }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isRealEstate ? 11 : 10 }}" class="text-center text-secondary py-4">
                        {{ $isRealEstate ? 'لا توجد عقارات وفق المرشحات الحالية.' : __('No properties found.') }}
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary">
            {{ $isRealEstate ? 'النتائج' : __('Showing') }}:
            <span>{{ $properties->firstItem() ?? 0 }}</span>–<span>{{ $properties->lastItem() ?? 0 }}</span>
            / <span>{{ $properties->total() }}</span>
        </p>
        <div class="ms-auto">{{ $properties->withQueryString()->links() }}</div>
    </div>
</div>
@endsection
