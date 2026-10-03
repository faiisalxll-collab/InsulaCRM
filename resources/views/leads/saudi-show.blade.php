@extends('layouts.app')

@section('title', $lead->full_name)
@section('page-title', $lead->full_name)

@section('content')
@php
    $statuses = \App\Services\CustomFieldService::getOptions('lead_status');
    $contactTypes = \App\Services\BusinessModeService::getRealEstateContactTypes();
    $temperatures = \App\Services\BusinessModeService::getRealEstateTemperatures();
    $propertyTypes = \App\Services\CustomFieldService::getOptions('property_type');

    $requestDeals = $lead->propertyRequests->flatMap(fn($request) => $request->deals);
    $allDeals = $lead->deals->concat($requestDeals)->unique('id')->sortByDesc('updated_at');
@endphp

<div dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="text-muted">{{ $contactTypes[$lead->contact_type] ?? 'عميل' }}</div>
            <h2 class="mb-0">{{ $lead->full_name }}</h2>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('properties.create', ['lead_id' => $lead->id]) }}" class="btn btn-primary">إضافة عقار للعميل</a>
            <a href="{{ route('property-requests.create', ['lead_id' => $lead->id]) }}" class="btn btn-outline-primary">إضافة طلب للعميل</a>
            <a href="{{ route('leads.edit', $lead) }}" class="btn btn-outline-secondary">تعديل العميل</a>
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">بيانات العميل</h3></div>
                <div class="card-body">
                    <div class="mb-2 d-flex justify-content-between gap-3">
                        <span class="text-muted">الجوال</span>
                        <strong>{{ $lead->phone ?? '—' }}</strong>
                    </div>
                    <div class="mb-2 d-flex justify-content-between gap-3">
                        <span class="text-muted">البريد</span>
                        <strong>{{ $lead->email ?? '—' }}</strong>
                    </div>
                    <div class="mb-2 d-flex justify-content-between gap-3">
                        <span class="text-muted">الحالة</span>
                        <strong>{{ $statuses[$lead->status] ?? $lead->status }}</strong>
                    </div>
                    <div class="mb-2 d-flex justify-content-between gap-3">
                        <span class="text-muted">الأولوية</span>
                        <strong>{{ $temperatures[$lead->temperature] ?? $lead->temperature }}</strong>
                    </div>
                    <div class="mb-2 d-flex justify-content-between gap-3">
                        <span class="text-muted">المصدر</span>
                        <strong>{{ \App\Services\BusinessModeService::getLeadSourceLabel($lead->lead_source, auth()->user()->tenant) }}</strong>
                    </div>
                    <div class="mb-2 d-flex justify-content-between gap-3">
                        <span class="text-muted">الوسيط</span>
                        <strong>{{ $lead->agent?->name ?? '—' }}</strong>
                    </div>
                    @if($lead->do_not_contact)
                        <div class="alert alert-danger mt-3 mb-0">هذا العميل محدد بعدم التواصل.</div>
                    @endif
                    @if($lead->notes)
                        <hr>
                        <div class="text-muted small">ملاحظات</div>
                        <div>{{ $lead->notes }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">عقارات العميل</h3>
                        <div class="text-muted small mt-1">العقارات التي يملكها أو يعرضها عبر المكتب.</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>العقار</th><th>العملية</th><th>السعر</th><th>الحالة</th><th></th></tr></thead>
                        <tbody>
                        @forelse($lead->properties as $property)
                            <tr>
                                <td>
                                    <strong>{{ $property->address }}</strong>
                                    <div class="text-muted small">{{ $property->district ? $property->district.'، ' : '' }}{{ $property->city }}</div>
                                </td>
                                <td>{{ $property->transaction_type === 'rent' ? 'إيجار' : 'بيع' }}</td>
                                <td>{{ Fmt::currency($property->list_price ?? $property->asking_price) }}</td>
                                <td>{{ ['active'=>'نشط','pending'=>'معلّق','sold'=>'مباع','leased'=>'مؤجر','withdrawn'=>'مسحوب','expired'=>'منتهي'][$property->listing_status] ?? $property->listing_status }}</td>
                                <td><a href="{{ route('properties.show', $property) }}" class="btn btn-sm btn-outline-primary">فتح</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">لا توجد عقارات مرتبطة بهذا العميل.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">طلبات العميل</h3>
                <div class="text-muted small mt-1">طلبات الشراء أو الإيجار والمطابقات الناتجة عنها.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>الطلب</th><th>الموقع</th><th>الميزانية</th><th>المطابقات</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                @forelse($lead->propertyRequests as $request)
                    <tr>
                        <td>
                            <strong>{{ $request->transaction_type === 'rent' ? 'إيجار' : 'شراء' }}</strong>
                            <div class="text-muted small">{{ $propertyTypes[$request->property_type] ?? $request->property_type }}</div>
                        </td>
                        <td>{{ $request->city }}{{ !empty($request->districts) ? ' — '.implode('، ', $request->districts) : '' }}</td>
                        <td>
                            {{ $request->min_price ? number_format((float) $request->min_price) : '0' }}
                            –
                            {{ $request->max_price ? number_format((float) $request->max_price) : 'مفتوح' }} ر.س
                        </td>
                        <td>{{ $request->matches->where('status', 'eligible')->count() }}</td>
                        <td>{{ \App\Models\PropertyRequest::STATUSES[$request->status] ?? $request->status }}</td>
                        <td><a href="{{ route('property-requests.show', $request) }}" class="btn btn-sm btn-outline-primary">فتح</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">لا توجد طلبات مرتبطة بهذا العميل.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">الصفقات</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>الصفقة</th><th>العقار</th><th>المرحلة</th><th>القيمة</th><th></th></tr></thead>
                        <tbody>
                        @forelse($allDeals as $deal)
                            <tr>
                                <td>{{ $deal->title }}</td>
                                <td>{{ $deal->property?->address ?? '—' }}</td>
                                <td>{{ \App\Models\Deal::stageLabel($deal->stage) }}</td>
                                <td>{{ $deal->contract_price !== null ? Fmt::currency($deal->contract_price) : '—' }}</td>
                                <td><a href="{{ route('deals.show', $deal) }}" class="btn btn-sm btn-outline-primary">فتح</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">لا توجد صفقات مرتبطة بهذا العميل.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">آخر النشاطات</h3></div>
                <div class="list-group list-group-flush">
                    @forelse($lead->activities->sortByDesc('logged_at')->take(8) as $activity)
                        <div class="list-group-item">
                            <div class="fw-medium">{{ $activity->subject ?: 'نشاط' }}</div>
                            @if($activity->body)<div class="text-muted small">{{ $activity->body }}</div>@endif
                            <div class="text-muted small mt-1">{{ $activity->logged_at?->format('Y-m-d H:i') }}</div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">لا توجد نشاطات مسجلة.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
