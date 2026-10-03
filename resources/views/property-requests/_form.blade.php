@php
    $editing = isset($propertyRequest);
    $districtsValue = old('districts_csv', $editing ? implode('، ', $propertyRequest->districts ?? []) : '');
    $selectedFacings = old('preferred_facings', $editing ? ($propertyRequest->preferred_facings ?? []) : []);
    $facings = [
        'north' => 'شمال',
        'south' => 'جنوب',
        'east' => 'شرق',
        'west' => 'غرب',
        'north_east' => 'شمال شرقي',
        'north_west' => 'شمال غربي',
        'south_east' => 'جنوب شرقي',
        'south_west' => 'جنوب غربي',
    ];
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">العميل</label>
        <select name="lead_id" class="form-select @error('lead_id') is-invalid @enderror">
            <option value="">بدون عميل مرتبط</option>
            @foreach($leads as $lead)
                <option value="{{ $lead->id }}" @selected((string) old('lead_id', $propertyRequest->lead_id ?? '') === (string) $lead->id)>
                    {{ $lead->first_name }} {{ $lead->last_name }}{{ $lead->phone ? ' — '.$lead->phone : '' }}
                </option>
            @endforeach
        </select>
        @error('lead_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @if(auth()->user()->isAdmin())
    <div class="col-md-6">
        <label class="form-label">الوسيط المسؤول</label>
        <select name="agent_id" class="form-select @error('agent_id') is-invalid @enderror">
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}" @selected((string) old('agent_id', $propertyRequest->agent_id ?? auth()->id()) === (string) $agent->id)>
                    {{ $agent->name }}
                </option>
            @endforeach
        </select>
        @error('agent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @endif

    <div class="col-md-3">
        <label class="form-label required">نوع العملية</label>
        <select name="transaction_type" class="form-select @error('transaction_type') is-invalid @enderror" required>
            @foreach($transactionTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('transaction_type', $propertyRequest->transaction_type ?? 'sale') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('transaction_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label required">نوع العقار</label>
        <select name="property_type" class="form-select @error('property_type') is-invalid @enderror" required>
            <option value="">اختر النوع</option>
            @foreach($propertyTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('property_type', $propertyRequest->property_type ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('property_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label required">المدينة</label>
        <input type="text" name="city" class="form-control @error('city') is-invalid @enderror"
               value="{{ old('city', $propertyRequest->city ?? 'الرياض') }}" required>
        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label required">حالة الطلب</label>
        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $propertyRequest->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label">الأحياء المقبولة</label>
        <input type="text" name="districts_csv" class="form-control @error('districts_csv') is-invalid @enderror"
               value="{{ $districtsValue }}" placeholder="مثال: نمار، ظهرة لبن، العوالي">
        <div class="form-hint">افصل بين الأحياء بفاصلة عربية أو إنجليزية.</div>
        @error('districts_csv') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">الميزانية من</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" name="min_price" class="form-control @error('min_price') is-invalid @enderror"
                   value="{{ old('min_price', $propertyRequest->min_price ?? '') }}">
            <span class="input-group-text">ر.س</span>
        </div>
        @error('min_price') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">الميزانية إلى</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" name="max_price" class="form-control @error('max_price') is-invalid @enderror"
                   value="{{ old('max_price', $propertyRequest->max_price ?? '') }}">
            <span class="input-group-text">ر.س</span>
        </div>
        @error('max_price') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">أقل مساحة</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" name="min_area_sqm" class="form-control @error('min_area_sqm') is-invalid @enderror"
                   value="{{ old('min_area_sqm', $propertyRequest->min_area_sqm ?? '') }}">
            <span class="input-group-text">م²</span>
        </div>
        @error('min_area_sqm') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">أقل غرف</label>
        <input type="number" min="0" name="min_bedrooms" class="form-control @error('min_bedrooms') is-invalid @enderror"
               value="{{ old('min_bedrooms', $propertyRequest->min_bedrooms ?? '') }}">
        @error('min_bedrooms') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">أقل عرض شارع</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" name="min_street_width_m" class="form-control @error('min_street_width_m') is-invalid @enderror"
                   value="{{ old('min_street_width_m', $propertyRequest->min_street_width_m ?? '') }}">
            <span class="input-group-text">م</span>
        </div>
        @error('min_street_width_m') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">أقصى عمر للعقار</label>
        <div class="input-group">
            <input type="number" min="0" name="max_property_age_years" class="form-control @error('max_property_age_years') is-invalid @enderror"
                   value="{{ old('max_property_age_years', $propertyRequest->max_property_age_years ?? '') }}">
            <span class="input-group-text">سنة</span>
        </div>
        @error('max_property_age_years') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-10">
        <label class="form-label">الواجهات المفضلة</label>
        <div class="d-flex flex-wrap gap-3 pt-2">
            @foreach($facings as $value => $label)
                <label class="form-check">
                    <input type="checkbox" class="form-check-input" name="preferred_facings[]" value="{{ $value }}"
                           @checked(in_array($value, (array) $selectedFacings, true))>
                    <span class="form-check-label">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('preferred_facings') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">التمويل</label>
        <input type="hidden" name="finance_required" value="0">
        <label class="form-check form-switch mt-2">
            <input type="checkbox" class="form-check-input" name="finance_required" value="1"
                   @checked((bool) old('finance_required', $propertyRequest->finance_required ?? false))>
            <span class="form-check-label">يشترط قبول التمويل</span>
        </label>
    </div>

    <div class="col-12">
        <label class="form-label">ملاحظات</label>
        <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $propertyRequest->notes ?? '') }}</textarea>
        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
