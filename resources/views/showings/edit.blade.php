@extends('layouts.app')

@section('title', 'تعديل المعاينة')
@section('page-title', 'تعديل المعاينة')

@section('content')
<div class="card" dir="rtl">
    <div class="card-header">
        <div>
            <h3 class="card-title">تعديل المعاينة</h3>
            <div class="text-muted small mt-1">حدّث الموعد أو نتيجة المعاينة بدون فقدان ارتباطها بالطلب والعقار.</div>
        </div>
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route('showings.update', $showing) }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">طلب العميل</label>
                    <select name="property_request_id" class="form-select @error('property_request_id') is-invalid @enderror">
                        <option value="">بدون طلب مرتبط</option>
                        @foreach($propertyRequests as $propertyRequest)
                            <option value="{{ $propertyRequest->id }}" @selected((string) old('property_request_id', $showing->property_request_id) === (string) $propertyRequest->id)>
                                طلب #{{ $propertyRequest->id }}
                                — {{ $propertyRequest->lead?->full_name ?? 'بدون عميل' }}
                                — {{ $propertyRequest->transaction_type === 'rent' ? 'إيجار' : 'شراء' }}
                            </option>
                        @endforeach
                    </select>
                    @error('property_request_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label required">العقار</label>
                    <select name="property_id" class="form-select @error('property_id') is-invalid @enderror" required>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" @selected((string) old('property_id', $showing->property_id) === (string) $property->id)>
                                {{ $property->address }}
                                {{ $property->district ? ' — '.$property->district : '' }}
                                — {{ $property->transaction_type === 'rent' ? 'إيجار' : 'بيع' }}
                            </option>
                        @endforeach
                    </select>
                    @error('property_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                @if(!$showing->property_request_id)
                <div class="col-md-6">
                    <label class="form-label">العميل</label>
                    <select name="lead_id" class="form-select @error('lead_id') is-invalid @enderror">
                        <option value="">بدون عميل</option>
                        @foreach($leads as $lead)
                            <option value="{{ $lead->id }}" @selected((string) old('lead_id', $showing->lead_id) === (string) $lead->id)>
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
                           value="{{ old('showing_date', $showing->showing_date->format('Y-m-d')) }}" required>
                    @error('showing_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label required">الوقت</label>
                    <input type="time" name="showing_time" class="form-control @error('showing_time') is-invalid @enderror"
                           value="{{ old('showing_time', $showing->showing_time) }}" required>
                    @error('showing_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">المدة</label>
                    <div class="input-group">
                        <input type="number" name="duration_minutes" min="15" max="480"
                               class="form-control @error('duration_minutes') is-invalid @enderror"
                               value="{{ old('duration_minutes', $showing->duration_minutes) }}">
                        <span class="input-group-text">دقيقة</span>
                    </div>
                    @error('duration_minutes') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                @if(auth()->user()->isAdmin())
                <div class="col-md-3">
                    <label class="form-label">الوسيط</label>
                    <select name="agent_id" class="form-select @error('agent_id') is-invalid @enderror">
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" @selected((string) old('agent_id', $showing->agent_id) === (string) $agent->id)>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('agent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                @endif

                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        @foreach(AppModelsShowing::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', $showing->status) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">النتيجة</label>
                    <select name="outcome" class="form-select">
                        <option value="">لم تُحدد بعد</option>
                        @foreach(AppModelsShowing::OUTCOMES as $key => $label)
                            <option value="{{ $key }}" @selected(old('outcome', $showing->outcome) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">ملاحظات العميل بعد المعاينة</label>
                    <textarea name="feedback" class="form-control" rows="2">{{ old('feedback', $showing->feedback) }}</textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">وسيط الطرف الآخر</label>
                    <input type="text" name="listing_agent_name" class="form-control"
                           value="{{ old('listing_agent_name', $showing->listing_agent_name) }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">جوال وسيط الطرف الآخر</label>
                    <input type="text" name="listing_agent_phone" class="form-control"
                           value="{{ old('listing_agent_phone', $showing->listing_agent_phone) }}">
                </div>

                <div class="col-12">
                    <label class="form-label">ملاحظات داخلية</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $showing->notes) }}</textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                <a href="{{ route('showings.show', $showing) }}" class="btn btn-outline-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
