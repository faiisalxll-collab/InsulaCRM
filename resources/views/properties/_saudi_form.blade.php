@php
    $facingOptions = [
        'north' => 'شمال',
        'south' => 'جنوب',
        'east' => 'شرق',
        'west' => 'غرب',
        'north_east' => 'شمال شرقي',
        'north_west' => 'شمال غربي',
        'south_east' => 'جنوب شرقي',
        'south_west' => 'جنوب غربي',
    ];
    $statusOptions = [
        'active' => 'نشط',
        'pending' => 'معلّق',
        'sold' => 'مباع',
        'withdrawn' => 'مسحوب',
        'expired' => 'منتهي',
    ];
@endphp

<div class="row g-3" dir="rtl">
    <div class="col-md-6">
        <label class="form-label required">المالك / العميل</label>
        <select name="lead_id" class="form-select @error('lead_id') is-invalid @enderror" required>
            <option value="">اختر العميل</option>
            @foreach($leads as $lead)
                <option value="{{ $lead->id }}" @selected((string) old('lead_id', $property->lead_id ?? '') === (string) $lead->id)>
                    {{ $lead->first_name }} {{ $lead->last_name }}{{ $lead->phone ? ' — '.$lead->phone : '' }}
                </option>
            @endforeach
        </select>
        @error('lead_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-hint">الوسيط المسؤول عن العقار يُستمد من العميل المرتبط به.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label required">نوع العملية</label>
        <select name="transaction_type" class="form-select @error('transaction_type') is-invalid @enderror" required>
            <option value="sale" @selected(old('transaction_type', $property->transaction_type ?? 'sale') === 'sale')>بيع</option>
            <option value="rent" @selected(old('transaction_type', $property->transaction_type ?? 'sale') === 'rent')>إيجار</option>
        </select>
        @error('transaction_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label required">نوع العقار</label>
        <select name="property_type" class="form-select @error('property_type') is-invalid @enderror" required>
            <option value="">اختر النوع</option>
            @foreach($propertyTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('property_type', $property->property_type ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('property_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label required">العنوان</label>
        <input type="text" name="address" class="form-control @error('address') is-invalid @enderror"
               value="{{ old('address', $property->address ?? '') }}" placeholder="مثال: شارع نجم الدين الأيوبي" required>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label required">المدينة</label>
        <input type="text" name="city" class="form-control @error('city') is-invalid @enderror"
               value="{{ old('city', $property->city ?? 'الرياض') }}" required>
        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">المنطقة</label>
        <input type="text" name="state" class="form-control @error('state') is-invalid @enderror"
               value="{{ old('state', $property->state ?? '') }}">
        @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">الرمز البريدي</label>
        <input type="text" name="zip_code" class="form-control @error('zip_code') is-invalid @enderror"
               value="{{ old('zip_code', $property->zip_code ?? '') }}" maxlength="10">
        @error('zip_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">الحي</label>
        <input type="text" name="district" class="form-control @error('district') is-invalid @enderror"
               value="{{ old('district', $property->district ?? '') }}">
        @error('district') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">رقم المخطط</label>
        <input type="text" name="plan_number" class="form-control @error('plan_number') is-invalid @enderror"
               value="{{ old('plan_number', $property->plan_number ?? '') }}">
        @error('plan_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">المساحة</label>
        <div class="input-group">
            <input type="number" name="area_sqm" step="0.01" min="0" class="form-control @error('area_sqm') is-invalid @enderror"
                   value="{{ old('area_sqm', $property->area_sqm ?? '') }}">
            <span class="input-group-text">م²</span>
        </div>
        @error('area_sqm') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">السعر</label>
        <div class="input-group">
            <input type="number" name="list_price" step="0.01" min="0" class="form-control @error('list_price') is-invalid @enderror"
                   value="{{ old('list_price', $property->list_price ?? $property->asking_price ?? '') }}">
            <span class="input-group-text">ر.س</span>
        </div>
        @error('list_price') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-hint">سعر المتر يُحسب تلقائيًا عند توفر السعر والمساحة.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label">حالة العرض</label>
        <select name="listing_status" class="form-select @error('listing_status') is-invalid @enderror">
            @foreach($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('listing_status', $property->listing_status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('listing_status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">الواجهة</label>
        <select name="facing" class="form-select @error('facing') is-invalid @enderror">
            <option value="">غير محدد</option>
            @foreach($facingOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('facing', $property->facing ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('facing') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">عرض الشارع</label>
        <div class="input-group">
            <input type="number" name="street_width_m" step="0.01" min="0" class="form-control @error('street_width_m') is-invalid @enderror"
                   value="{{ old('street_width_m', $property->street_width_m ?? '') }}">
            <span class="input-group-text">م</span>
        </div>
        @error('street_width_m') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">عمر العقار</label>
        <input type="number" name="property_age_years" min="0" class="form-control @error('property_age_years') is-invalid @enderror"
               value="{{ old('property_age_years', $property->property_age_years ?? '') }}">
        @error('property_age_years') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">غرف النوم</label>
        <input type="number" name="bedrooms" min="0" class="form-control @error('bedrooms') is-invalid @enderror"
               value="{{ old('bedrooms', $property->bedrooms ?? '') }}">
        @error('bedrooms') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">دورات المياه</label>
        <input type="number" name="bathrooms" min="0" class="form-control @error('bathrooms') is-invalid @enderror"
               value="{{ old('bathrooms', $property->bathrooms ?? '') }}">
        @error('bathrooms') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">الأدوار</label>
        <input type="number" name="floors" min="0" class="form-control @error('floors') is-invalid @enderror"
               value="{{ old('floors', $property->floors ?? '') }}">
        @error('floors') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">الوحدات</label>
        <input type="number" name="units" min="0" class="form-control @error('units') is-invalid @enderror"
               value="{{ old('units', $property->units ?? '') }}">
        @error('units') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <input type="hidden" name="furnished" value="0">
        <label class="form-check form-switch mt-4">
            <input type="checkbox" name="furnished" value="1" class="form-check-input" @checked((bool) old('furnished', $property->furnished ?? false))>
            <span class="form-check-label">مفروش</span>
        </label>
    </div>

    <div class="col-md-3">
        <input type="hidden" name="finance_eligible" value="0">
        <label class="form-check form-switch mt-4">
            <input type="checkbox" name="finance_eligible" value="1" class="form-check-input" @checked((bool) old('finance_eligible', $property->finance_eligible ?? false))>
            <span class="form-check-label">يقبل التمويل</span>
        </label>
    </div>

    <div class="col-md-3">
        <label class="form-label">تاريخ العرض</label>
        <input type="date" name="listed_at" class="form-control @error('listed_at') is-invalid @enderror"
               value="{{ old('listed_at', isset($property) && $property->listed_at ? $property->listed_at->format('Y-m-d') : '') }}">
        @error('listed_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">إحداثيات الموقع</label>
        <div class="row g-1">
            <div class="col-6">
                <input type="number" name="latitude" step="0.0000001" min="-90" max="90" class="form-control" placeholder="Latitude"
                       value="{{ old('latitude', $property->latitude ?? '') }}">
            </div>
            <div class="col-6">
                <input type="number" name="longitude" step="0.0000001" min="-180" max="180" class="form-control" placeholder="Longitude"
                       value="{{ old('longitude', $property->longitude ?? '') }}">
            </div>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">ملاحظات</label>
        <textarea name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $property->notes ?? '') }}</textarea>
        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
