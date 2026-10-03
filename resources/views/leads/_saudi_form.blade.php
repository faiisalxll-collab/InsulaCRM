@php
    $sourceOptions = \App\Services\CustomFieldService::getOptions('lead_source');
    $statusOptions = \App\Services\CustomFieldService::getOptions('lead_status');
    $contactTypes = \App\Services\BusinessModeService::getRealEstateContactTypes();
    $temperatures = \App\Services\BusinessModeService::getRealEstateTemperatures();

    $currentSource = old('lead_source', $lead->lead_source ?? '');
    if ($currentSource && !isset($sourceOptions[$currentSource])) {
        $sourceOptions[$currentSource] = \App\Services\BusinessModeService::getLeadSourceLabel(
            $currentSource,
            auth()->user()->tenant
        );
    }
@endphp

<input type="hidden" name="timezone" value="{{ auth()->user()->tenant->timezone ?? 'Asia/Riyadh' }}">

<div class="row g-3" dir="rtl">
    <div class="col-md-6">
        <label class="form-label required">الاسم الأول</label>
        <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror"
               value="{{ old('first_name', $lead->first_name ?? '') }}" required>
        @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label required">اسم العائلة</label>
        <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror"
               value="{{ old('last_name', $lead->last_name ?? '') }}" required>
        @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">الجوال</label>
        <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $lead->phone ?? '') }}" placeholder="05xxxxxxxx">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">البريد الإلكتروني</label>
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $lead->email ?? '') }}">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">نوع العميل</label>
        <select name="contact_type" class="form-select @error('contact_type') is-invalid @enderror">
            <option value="">غير محدد</option>
            @foreach($contactTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('contact_type', $lead->contact_type ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('contact_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label required">مصدر العميل</label>
        <select name="lead_source" class="form-select @error('lead_source') is-invalid @enderror" required>
            @foreach($sourceOptions as $value => $label)
                <option value="{{ $value }}" @selected($currentSource === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('lead_source') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label required">الحالة</label>
        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $lead->status ?? 'new') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label required">أولوية المتابعة</label>
        <select name="temperature" class="form-select @error('temperature') is-invalid @enderror" required>
            @foreach($temperatures as $value => $label)
                <option value="{{ $value }}" @selected(old('temperature', $lead->temperature ?? 'cold') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('temperature') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @if(auth()->user()->isAdmin())
        <div class="col-md-6">
            <label class="form-label required">الوسيط المسؤول</label>
            <select name="agent_id" class="form-select @error('agent_id') is-invalid @enderror" required>
                <option value="">اختر الوسيط</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" @selected((string) old('agent_id', $lead->agent_id ?? auth()->id()) === (string) $agent->id)>
                        {{ $agent->name }}
                    </option>
                @endforeach
            </select>
            @error('agent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @else
        <input type="hidden" name="agent_id" value="{{ auth()->id() }}">
    @endif

    <div class="col-md-6 d-flex align-items-end">
        <label class="form-check form-switch mb-2">
            <input type="hidden" name="do_not_contact" value="0">
            <input type="checkbox" name="do_not_contact" value="1" class="form-check-input"
                   @checked((bool) old('do_not_contact', $lead->do_not_contact ?? false))>
            <span class="form-check-label">عدم التواصل مع العميل</span>
        </label>
    </div>

    @php
        $customFieldDefs = \App\Models\CustomFieldDefinition::forEntity('lead');
        $customValues = $lead->custom_fields ?? [];
    @endphp
    @foreach($customFieldDefs as $field)
        <div class="col-md-4">
            <label class="form-label {{ $field->required ? 'required' : '' }}">{{ $field->name }}</label>
            @if($field->field_type === 'textarea')
                <textarea name="custom_fields[{{ $field->slug }}]" class="form-control" rows="2" {{ $field->required ? 'required' : '' }}>{{ old('custom_fields.'.$field->slug, $customValues[$field->slug] ?? '') }}</textarea>
            @elseif($field->field_type === 'select')
                <select name="custom_fields[{{ $field->slug }}]" class="form-select" {{ $field->required ? 'required' : '' }}>
                    <option value="">اختر</option>
                    @foreach($field->options ?? [] as $option)
                        <option value="{{ $option }}" @selected(old('custom_fields.'.$field->slug, $customValues[$field->slug] ?? '') == $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @elseif($field->field_type === 'checkbox')
                <input type="hidden" name="custom_fields[{{ $field->slug }}]" value="0">
                <label class="form-check mt-2">
                    <input type="checkbox" name="custom_fields[{{ $field->slug }}]" value="1" class="form-check-input"
                           @checked((bool) old('custom_fields.'.$field->slug, $customValues[$field->slug] ?? false))>
                    <span class="form-check-label">نعم</span>
                </label>
            @else
                <input type="{{ $field->field_type === 'date' ? 'date' : ($field->field_type === 'number' ? 'number' : 'text') }}"
                       name="custom_fields[{{ $field->slug }}]"
                       class="form-control"
                       value="{{ old('custom_fields.'.$field->slug, $customValues[$field->slug] ?? '') }}"
                       {{ $field->required ? 'required' : '' }}>
            @endif
        </div>
    @endforeach

    <div class="col-12">
        <label class="form-label">ملاحظات</label>
        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="4"
                  placeholder="احتياج العميل، وقت الشراء أو البيع، ملاحظات التواصل...">{{ old('notes', $lead->notes ?? '') }}</textarea>
        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
