@extends('layouts.app')

@section('title', 'جدولة معاينة')
@section('page-title', 'جدولة معاينة')

@section('content')
@php
    $selectedRequestId = old('property_request_id', $selectedRequest?->id);
    $selectedProperty = old('property_id', $selectedPropertyId);
@endphp

<div class="card" dir="rtl">
    <div class="card-header">
        <div>
            <h3 class="card-title">جدولة معاينة عقار</h3>
            <div class="text-muted small mt-1">اربط المعاينة بطلب العميل والعقار المطابق حتى يستمر المسار تلقائيًا حتى التفاوض والصفقة.</div>
        </div>
    </div>

    <div class="card-body">
        @if($selectedRequest && $selectedPropertyId)
            <div class="alert alert-success">
                تم فتح المعاينة من مطابقة معتمدة للطلب #{{ $selectedRequest->id }}. بيانات العميل والوسيط ستؤخذ من الطلب تلقائيًا.
            </div>
        @endif

        <form method="POST" action="{{ route('showings.store') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">طلب العميل</label>
                    <select name="property_request_id" class="form-select @error('property_request_id') is-invalid @enderror">
                        <option value="">بدون طلب مرتبط</option>
                        @foreach($propertyRequests as $propertyRequest)
                            <option value="{{ $propertyRequest->id }}" @selected((string) $selectedRequestId === (string) $propertyRequest->id)>
                                طلب #{{ $propertyRequest->id }}
                                — {{ $propertyRequest->lead?->full_name ?? 'بدون عميل' }}
                                — {{ $propertyRequest->transaction_type === 'rent' ? 'إيجار' : 'شراء' }}
                                — {{ $propertyRequest->city }}
                            </option>
                        @endforeach
                    </select>
                    @error('property_request_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-hint">عند اختيار طلب، النظام يثبت العميل والوسيط من الطلب نفسه.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label required">العقار</label>
                    <select name="property_id" class="form-select @error('property_id') is-invalid @enderror" required>
                        <option value="">اختر العقار</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" @selected((string) $selectedProperty === (string) $property->id)>
                                {{ $property->address }}
                                {{ $property->district ? ' — '.$property->district : '' }}
                                — {{ $property->transaction_type === 'rent' ? 'إيجار' : 'بيع' }}
                            </option>
                        @endforeach
                    </select>
                    @error('property_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                @if(!$selectedRequest)
                <div class="col-md-6">
                    <label class="form-label">العميل</label>
                    <select name="lead_id" class="form-select @error('lead_id') is-invalid @enderror">
                        <option value="">بدون عميل</option>
                        @foreach($leads as $lead)
                            <option value="{{ $lead->id }}" @selected((string) old('lead_id') === (string) $lead->id)>
                                {{ $lead->first_name }} {{ $lead->last_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('lead_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                @endif

                <div class="col-md-3">
                    <label class="form-label required">التاريخ</label>
                    <input type="date" name="showing_date" class="form-control @error('showing_date') is-invalid @enderror"
                           value="{{ old('showing_date', date('Y-m-d')) }}" required>
                    @error('showing_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label required">الوقت</label>
                    <input type="time" name="showing_time" class="form-control @error('showing_time') is-invalid @enderror"
                           value="{{ old('showing_time', '17:00') }}" required>
                    @error('showing_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">المدة</label>
                    <div class="input-group">
                        <input type="number" name="duration_minutes" min="15" max="480"
                               class="form-control @error('duration_minutes') is-invalid @enderror"
                               value="{{ old('duration_minutes', 30) }}">
                        <span class="input-group-text">دقيقة</span>
                    </div>
                    @error('duration_minutes') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                @if(auth()->user()->isAdmin())
                <div class="col-md-3">
                    <label class="form-label">الوسيط</label>
                    <select name="agent_id" class="form-select @error('agent_id') is-invalid @enderror">
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" @selected((string) old('agent_id', $selectedRequest?->agent_id ?? auth()->id()) === (string) $agent->id)>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('agent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                @endif

                <div class="col-md-6">
                    <label class="form-label">وسيط الطرف الآخر</label>
                    <input type="text" name="listing_agent_name" class="form-control"
                           value="{{ old('listing_agent_name') }}" placeholder="اختياري">
                </div>

                <div class="col-md-6">
                    <label class="form-label">جوال وسيط الطرف الآخر</label>
                    <input type="text" name="listing_agent_phone" class="form-control"
                           value="{{ old('listing_agent_phone') }}">
                </div>

                <div class="col-12">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">حفظ المعاينة</button>
                <a href="{{ $selectedRequest ? route('property-requests.show', $selectedRequest) : route('showings.index') }}"
                   class="btn btn-outline-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
