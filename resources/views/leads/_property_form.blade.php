<div class="card mb-3" id="property-section">
    <div class="card-header">
        <h3 class="card-title">{{ __('Property Details') }}</h3>
        <div class="card-actions">
            <a class="btn btn-ghost-secondary btn-sm" data-bs-toggle="collapse" href="#section-property" aria-expanded="true" aria-label="{{ __('Toggle section') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="6 9 12 15 18 9"/></svg>
            </a>
        </div>
    </div>
    <div class="card-body collapse show" id="section-property">
        @if($lead->property)
        <div class="mb-3 p-3 rounded" style="background: #f6f8fb;">
            <div class="row">
                <div class="col-md-3">
                    <small class="text-secondary d-block">{{ __('Condition') }}</small>
                    <strong>{{ __(ucfirst($lead->property->condition ?? '-')) }}</strong>
                </div>
                <div class="col-md-2">
                    <small class="text-secondary d-block">{{ Fmt::areaUnit() }}</small>
                    <strong>{{ $lead->property->square_footage ? Fmt::area($lead->property->square_footage) : '-' }}</strong>
                </div>
                <div class="col-md-2">
                    <small class="text-secondary d-block">{{ __('Year Built') }}</small>
                    <strong>{{ $lead->property->year_built ?? '-' }}</strong>
                </div>
                @if(($businessMode ?? 'wholesale') === 'wholesale')
                <div class="col-md-2">
                    <small class="text-secondary d-block">{{ __('MAO') }}</small>
                    <strong class="{{ ($lead->property->mao ?? 0) >= 0 ? 'text-green' : 'text-red' }}">
                        {{ Fmt::currency($lead->property->mao ?? 0, 0) }}
                    </strong>
                </div>
                <div class="col-md-3">
                    <small class="text-secondary d-block">{{ __('Distress Markers') }}</small>
                    @if(!empty($lead->property->distress_markers) && is_array($lead->property->distress_markers))
                        @foreach($lead->property->distress_markers as $marker)
                            <span class="badge bg-orange-lt me-1">{{ __(ucwords(str_replace('_', ' ', $marker))) }}</span>
                        @endforeach
                    @else
                        <span class="text-secondary">{{ __('None') }}</span>
                    @endif
                </div>
                @else
                <div class="col-md-2">
                    <small class="text-secondary d-block">{{ __('List Price') }}</small>
                    <strong>{{ $lead->property->list_price ? Fmt::currency($lead->property->list_price, 0) : '-' }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-secondary d-block">{{ __('Listing Status') }}</small>
                    @php $ls = $lead->property->listing_status; @endphp
                    @if($ls)
                        <span class="badge bg-{{ $ls === 'active' ? 'green' : ($ls === 'pending' ? 'yellow' : ($ls === 'sold' ? 'blue' : 'secondary')) }}-lt">{{ __(ucfirst($ls)) }}</span>
                    @else
                        <span class="text-secondary">-</span>
                    @endif
                </div>
                @endif
            </div>
            @if(auth()->user()->tenant->ai_enabled)
            <div class="mt-2 d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-purple btn-sm ai-prop-btn" data-action="offer-strategy" data-prop-id="{{ $lead->property->id }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-sparkles" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z"/></svg>
                    {{ __('AI Offer Strategy') }}
                </button>
                <button type="button" class="btn btn-outline-purple btn-sm ai-prop-btn" data-action="property-description" data-prop-id="{{ $lead->property->id }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-sparkles" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z"/></svg>
                    {{ __('AI Property Description') }}
                </button>
                <button type="button" class="btn btn-outline-purple btn-sm ai-prop-btn" data-action="comparable-sales" data-prop-id="{{ $lead->property->id }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-sparkles" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z"/></svg>
                    {{ __('AI Comparable Sales') }}
                </button>
            </div>
            @endif
        </div>
        @endif
        <form action="{{ route('leads.property.store', $lead) }}" method="POST" id="property-form">
            @csrf
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label required">{{ __('Address') }}</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address', $lead->property->address ?? '') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label required">{{ __('City') }}</label>
                    <input type="text" name="city" class="form-control" value="{{ old('city', $lead->property->city ?? '') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label {{ ($businessMode ?? 'wholesale') === 'wholesale' ? 'required' : '' }}">
                        {{ ($businessMode ?? 'wholesale') === 'realestate' ? 'المنطقة' : Fmt::stateLabel() }}
                    </label>
                    <input type="text" name="state" class="form-control" maxlength="{{ ($businessMode ?? 'wholesale') === 'realestate' ? 100 : Fmt::stateMaxLength() }}" value="{{ old('state', $lead->property->state ?? '') }}" {{ ($businessMode ?? 'wholesale') === 'wholesale' ? 'required' : '' }}>
                </div>
                <div class="col-md-2">
                    <label class="form-label {{ ($businessMode ?? 'wholesale') === 'wholesale' ? 'required' : '' }}">
                        {{ ($businessMode ?? 'wholesale') === 'realestate' ? 'الرمز البريدي' : Fmt::postalCodeLabel() }}
                    </label>
                    <input type="text" name="zip_code" class="form-control" maxlength="{{ Fmt::postalCodeMaxLength() }}" value="{{ old('zip_code', $lead->property->zip_code ?? '') }}" {{ ($businessMode ?? 'wholesale') === 'wholesale' ? 'required' : '' }}>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label required">{{ __('Property Type') }}</label>
                    <select name="property_type" class="form-select" required>
                        @foreach(\App\Services\CustomFieldService::getOptions('property_type') as $val => $label)
                            <option value="{{ $val }}" {{ old('property_type', $lead->property->property_type ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Condition') }}</label>
                    <select name="condition" class="form-select">
                        <option value="">{{ __('Select...') }}</option>
                        @foreach(\App\Services\CustomFieldService::getOptions('property_condition') as $val => $label)
                            <option value="{{ $val }}" {{ old('condition', $lead->property->condition ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Bedrooms') }}</label>
                    <input type="number" name="bedrooms" class="form-control" min="0" value="{{ old('bedrooms', $lead->property->bedrooms ?? '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Bathrooms') }}</label>
                    <input type="number" name="bathrooms" class="form-control" min="0" step="0.5" value="{{ old('bathrooms', $lead->property->bathrooms ?? '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Year Built') }}</label>
                    <input type="number" name="year_built" class="form-control" min="1800" max="{{ date('Y') }}" value="{{ old('year_built', $lead->property->year_built ?? '') }}">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">{{ Fmt::areaLabel() }}</label>
                    <input type="number" name="square_footage" class="form-control" min="0" value="{{ old('square_footage', $lead->property->square_footage ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ Fmt::lotSizeLabel() }}</label>
                    <input type="number" name="lot_size" class="form-control" step="0.01" min="0" value="{{ old('lot_size', $lead->property->lot_size ?? '') }}">
                </div>
                @if(($businessMode ?? 'wholesale') === 'wholesale')
                <div class="col-md-4">
                    <label class="form-label">{{ __('Distress Markers') }}</label>
                    @php $currentMarkers = old('distress_markers', $lead->property->distress_markers ?? []); @endphp
                    <select name="distress_markers[]" class="form-select" multiple size="5">
                        @foreach(\App\Services\CustomFieldService::getOptions('distress_markers') as $val => $label)
                            <option value="{{ $val }}" {{ is_array($currentMarkers) && in_array($val, $currentMarkers) ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <small class="text-secondary">{{ __('Hold Ctrl/Cmd to select multiple') }}</small>
                </div>
                @endif
            </div>
            @if(($businessMode ?? 'wholesale') === 'realestate')
            <div class="row mb-3" dir="rtl">
                <div class="col-md-3">
                    <label class="form-label required">نوع العملية</label>
                    <select name="transaction_type" class="form-select" required>
                        <option value="sale" {{ old('transaction_type', $lead->property->transaction_type ?? 'sale') === 'sale' ? 'selected' : '' }}>بيع</option>
                        <option value="rent" {{ old('transaction_type', $lead->property->transaction_type ?? 'sale') === 'rent' ? 'selected' : '' }}>إيجار</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الحي</label>
                    <input type="text" name="district" class="form-control" value="{{ old('district', $lead->property->district ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">رقم المخطط</label>
                    <input type="text" name="plan_number" class="form-control" value="{{ old('plan_number', $lead->property->plan_number ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">المساحة م²</label>
                    <input type="number" name="area_sqm" class="form-control" step="0.01" min="0" value="{{ old('area_sqm', $lead->property->area_sqm ?? '') }}">
                </div>
            </div>
            <div class="row mb-3" dir="rtl">
                <div class="col-md-3">
                    <label class="form-label">الواجهة</label>
                    <select name="facing" class="form-select">
                        <option value="">غير محدد</option>
                        @foreach(['north' => 'شمال', 'south' => 'جنوب', 'east' => 'شرق', 'west' => 'غرب', 'north_east' => 'شمال شرقي', 'north_west' => 'شمال غربي', 'south_east' => 'جنوب شرقي', 'south_west' => 'جنوب غربي'] as $val => $label)
                            <option value="{{ $val }}" {{ old('facing', $lead->property->facing ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">عرض الشارع (م)</label>
                    <input type="number" name="street_width_m" class="form-control" step="0.01" min="0" value="{{ old('street_width_m', $lead->property->street_width_m ?? '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">عمر العقار</label>
                    <input type="number" name="property_age_years" class="form-control" min="0" value="{{ old('property_age_years', $lead->property->property_age_years ?? '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">الأدوار</label>
                    <input type="number" name="floors" class="form-control" min="0" value="{{ old('floors', $lead->property->floors ?? '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">الوحدات</label>
                    <input type="number" name="units" class="form-control" min="0" value="{{ old('units', $lead->property->units ?? '') }}">
                </div>
            </div>
            <div class="row mb-3" dir="rtl">
                <div class="col-md-3">
                    <input type="hidden" name="furnished" value="0">
                    <label class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="furnished" value="1" {{ old('furnished', $lead->property->furnished ?? false) ? 'checked' : '' }}>
                        <span class="form-check-label">مفروش</span>
                    </label>
                </div>
                <div class="col-md-3">
                    <input type="hidden" name="finance_eligible" value="0">
                    <label class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="finance_eligible" value="1" {{ old('finance_eligible', $lead->property->finance_eligible ?? false) ? 'checked' : '' }}>
                        <span class="form-check-label">يقبل التمويل</span>
                    </label>
                </div>
                <div class="col-md-3">
                    <label class="form-label">خط العرض</label>
                    <input type="number" name="latitude" class="form-control" step="0.0000001" min="-90" max="90" value="{{ old('latitude', $lead->property->latitude ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">خط الطول</label>
                    <input type="number" name="longitude" class="form-control" step="0.0000001" min="-180" max="180" value="{{ old('longitude', $lead->property->longitude ?? '') }}">
                </div>
            </div>
            @endif

            @if(($businessMode ?? 'wholesale') === 'wholesale')
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">{{ __('Estimated Value') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="estimated_value" class="form-control" step="0.01" min="0" value="{{ old('estimated_value', $lead->property->estimated_value ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('After Repair Value') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="after_repair_value" id="after_repair_value" class="form-control calc-field" step="0.01" min="0" value="{{ old('after_repair_value', $lead->property->after_repair_value ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Repair Estimate') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="repair_estimate" id="repair_estimate" class="form-control calc-field" step="0.01" min="0" value="{{ old('repair_estimate', $lead->property->repair_estimate ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('MAO') }}</label>
                    <div class="form-control-plaintext">
                        <strong id="mao-display" class="h4">$0.00</strong>
                        <br><small class="text-secondary">{{ __('(ARV x 70%) - Repairs') }}</small>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('Asking Price') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="asking_price" class="form-control" step="0.01" min="0" value="{{ old('asking_price', $lead->property->asking_price ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Our Offer') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="our_offer" id="our_offer" class="form-control calc-field" step="0.01" min="0" value="{{ old('our_offer', $lead->property->our_offer ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Assignment Fee') }}</label>
                    <div class="form-control-plaintext">
                        <strong id="assignment-fee" class="h4">$0.00</strong>
                    </div>
                </div>
            </div>
            @else
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('List Price') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="list_price" class="form-control" step="0.01" min="0" value="{{ old('list_price', $lead->property->list_price ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Listing Status') }}</label>
                    <select name="listing_status" class="form-select">
                        <option value="">{{ __('Select...') }}</option>
                        @foreach(['active' => __('Active'), 'pending' => __('Pending'), 'sold' => __('Sold'), 'withdrawn' => __('Withdrawn'), 'expired' => __('Expired')] as $val => $label)
                            <option value="{{ $val }}" {{ old('listing_status', $lead->property->listing_status ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Asking Price') }} ({{ Fmt::currencySymbol() }})</label>
                    <input type="number" name="asking_price" class="form-control" step="0.01" min="0" value="{{ old('asking_price', $lead->property->asking_price ?? '') }}">
                </div>
            </div>
            @endif
            <div class="mb-3">
                <label class="form-label">{{ __('Notes') }}</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $lead->property->notes ?? '') }}</textarea>
            </div>
            <div class="text-end">
                <button type="submit" class="btn btn-primary">{{ __('Save Property') }}</button>
            </div>
        </form>
    </div>
</div>

@if(($businessMode ?? 'wholesale') === 'wholesale')
@push('scripts')
<script>
function calculateMAO() {
    const arv = parseFloat(document.getElementById('after_repair_value').value) || 0;
    const repair = parseFloat(document.getElementById('repair_estimate').value) || 0;
    const maoEl = document.getElementById('mao-display');

    if (arv && repair) {
        const mao = (arv * 0.70) - repair;
        maoEl.textContent = '{{ Fmt::currencySymbol() }}' + mao.toLocaleString('{{ Fmt::jsLocale() }}', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        maoEl.className = mao >= 0 ? 'h4 text-green' : 'h4 text-red';
    } else {
        maoEl.textContent = '{{ Fmt::currencySymbol() }}0.00';
        maoEl.className = 'h4';
    }
}

function calculateAssignmentFee() {
    const ourOffer = parseFloat(document.getElementById('our_offer').value) || 0;
    const arv = parseFloat(document.getElementById('after_repair_value').value) || 0;
    const repair = parseFloat(document.getElementById('repair_estimate').value) || 0;

    if (ourOffer && arv && repair) {
        const mao = (arv * 0.70) - repair;
        const fee = mao - ourOffer;
        const feeEl = document.getElementById('assignment-fee');
        feeEl.textContent = '{{ Fmt::currencySymbol() }}' + fee.toLocaleString('{{ Fmt::jsLocale() }}', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        feeEl.className = fee >= 0 ? 'h4 text-green' : 'h4 text-red';
    } else {
        document.getElementById('assignment-fee').textContent = '{{ Fmt::currencySymbol() }}0.00';
        document.getElementById('assignment-fee').className = 'h4';
    }
}

document.querySelectorAll('.calc-field').forEach(function(input) {
    input.addEventListener('input', function() {
        calculateMAO();
        calculateAssignmentFee();
    });
});

calculateMAO();
calculateAssignmentFee();
</script>
@endpush
@endif
