@extends('layouts.app')

@section('title', 'طلبات العقار')
@section('page-title', 'طلبات العقار')

@section('content')
<div dir="rtl">
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">طلبات الشراء والإيجار</h3>
                <div class="text-muted small mt-1">طلبات العملاء ومعايير البحث والمطابقات الحالية.</div>
            </div>
            <div class="card-actions">
                <a href="{{ route('property-requests.create') }}" class="btn btn-primary">طلب جديد</a>
            </div>
        </div>

        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">الكل</option>
                        @foreach(AppModelsPropertyRequest::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">العملية</label>
                    <select name="transaction_type" class="form-select form-select-sm">
                        <option value="">بيع وإيجار</option>
                        @foreach(AppModelsPropertyRequest::TRANSACTION_TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(request('transaction_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">نوع العقار</label>
                    <select name="property_type" class="form-select form-select-sm">
                        <option value="">كل الأنواع</option>
                        @foreach($propertyTypes as $value => $label)
                            <option value="{{ $value }}" @selected(request('property_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">المدينة</label>
                    <input name="city" class="form-control form-control-sm" value="{{ request('city') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">بحث</label>
                    <input name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="اسم العميل أو الجوال أو المدينة">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-primary">تصفية</button>
                    <a href="{{ route('property-requests.index') }}" class="btn btn-sm btn-outline-secondary">مسح</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>العميل</th>
                        <th>الطلب</th>
                        <th>الموقع</th>
                        <th>الميزانية</th>
                        <th>المطابقات</th>
                        <th>الوسيط</th>
                        <th>الحالة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($propertyRequests as $propertyRequest)
                    <tr>
                        <td>
                            @if($propertyRequest->lead)
                                <a href="{{ route('leads.show', $propertyRequest->lead) }}">{{ $propertyRequest->lead->full_name }}</a>
                            @else
                                <span class="text-muted">غير مرتبط</span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ AppModelsPropertyRequest::TRANSACTION_TYPES[$propertyRequest->transaction_type] ?? $propertyRequest->transaction_type }}</strong>
                            <div class="text-muted small">{{ $propertyTypes[$propertyRequest->property_type] ?? $propertyRequest->property_type }}</div>
                        </td>
                        <td>
                            {{ $propertyRequest->city }}
                            @if(!empty($propertyRequest->districts))
                                <div class="text-muted small">{{ implode('، ', array_slice($propertyRequest->districts, 0, 3)) }}</div>
                            @endif
                        </td>
                        <td>
                            @if($propertyRequest->min_price || $propertyRequest->max_price)
                                {{ $propertyRequest->min_price ? number_format((float) $propertyRequest->min_price) : '0' }}
                                –
                                {{ $propertyRequest->max_price ? number_format((float) $propertyRequest->max_price) : 'مفتوح' }} ر.س
                            @else
                                <span class="text-muted">مفتوحة</span>
                            @endif
                        </td>
                        <td><span class="badge bg-blue">{{ $propertyRequest->eligible_matches_count }}</span></td>
                        <td>{{ $propertyRequest->agent?->name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $propertyRequest->status === 'active' ? 'green' : ($propertyRequest->status === 'paused' ? 'yellow' : 'secondary') }}">
                                {{ AppModelsPropertyRequest::STATUSES[$propertyRequest->status] ?? $propertyRequest->status }}
                            </span>
                        </td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('property-requests.show', $propertyRequest) }}">فتح</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">لا توجد طلبات بعد.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($propertyRequests->hasPages())
            <div class="card-footer">{{ $propertyRequests->links() }}</div>
        @endif
    </div>
</div>
@endsection
