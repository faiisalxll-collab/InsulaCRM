@php
    $offers = $deal->offers ?? collect();
    $clientName = trim((string) ($deal->propertyRequest?->lead?->full_name ?? 'العميل'));
    $statusLabels = [
        'pending' => 'قيد المراجعة',
        'countered' => 'عرض مقابل',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض',
        'withdrawn' => 'مسحوب',
        'expired' => 'منتهي',
    ];
    $statusColors = [
        'pending' => 'bg-blue-lt',
        'countered' => 'bg-yellow-lt',
        'accepted' => 'bg-green-lt',
        'rejected' => 'bg-red-lt',
        'withdrawn' => 'bg-secondary-lt',
        'expired' => 'bg-secondary-lt',
    ];
@endphp

<div class="card mb-3" id="offers-card" dir="rtl">
    <div class="card-header">
        <div>
            <h3 class="card-title">العروض والتفاوض</h3>
            <div class="text-muted small mt-1">سجل عرض العميل، والعرض المقابل، ثم القبول أو الرفض.</div>
        </div>
        <div class="card-actions">
            <button type="button" class="btn btn-sm btn-primary" id="offers-toggle-form-btn">إضافة عرض</button>
        </div>
    </div>

    <div class="card-body">
        <div id="offers-form-wrapper" style="display:none;" class="mb-3">
            <form method="POST" action="{{ route('deals.storeOffer', $deal) }}">
                @csrf
                <input type="hidden" name="buyer_name" value="{{ $clientName }}">

                <div class="border rounded p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">العميل</label>
                            <input type="text" class="form-control form-control-sm" value="{{ $clientName }}" disabled>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">قيمة العرض</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="offer_price" class="form-control" step="0.01" min="0" required>
                                <span class="input-group-text">{{ Fmt::currencySymbol() }}</span>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">العربون</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="earnest_money" class="form-control" step="0.01" min="0">
                                <span class="input-group-text">{{ Fmt::currencySymbol() }}</span>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">طريقة الشراء</label>
                            <select name="financing_type" class="form-select form-select-sm">
                                <option value="">غير محدد</option>
                                <option value="cash">نقدي</option>
                                <option value="bank_finance">تمويل بنكي</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">صلاحية العرض</label>
                            <input type="datetime-local" name="expiration_date" class="form-control form-control-sm">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">وسيط الطرف الآخر</label>
                            <input type="text" name="buyer_agent_name" class="form-control form-control-sm" maxlength="255">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">جوال وسيط الطرف الآخر</label>
                            <input type="text" name="buyer_agent_phone" class="form-control form-control-sm" maxlength="50">
                        </div>

                        <div class="col-12">
                            <label class="form-label">شروط العرض</label>
                            <div class="d-flex flex-wrap gap-3">
                                <label class="form-check">
                                    <input type="checkbox" name="contingencies[]" value="financing" class="form-check-input">
                                    <span class="form-check-label">الحصول على التمويل</span>
                                </label>
                                <label class="form-check">
                                    <input type="checkbox" name="contingencies[]" value="property_inspection" class="form-check-input">
                                    <span class="form-check-label">فحص العقار</span>
                                </label>
                                <label class="form-check">
                                    <input type="checkbox" name="contingencies[]" value="vacant_possession" class="form-check-input">
                                    <span class="form-check-label">الإخلاء والتسليم</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">حفظ العرض</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="offers-cancel-form-btn">إلغاء</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @forelse($offers->sortByDesc('created_at') as $offer)
            <div class="border rounded p-3 mb-2 {{ $offer->status === 'accepted' ? 'border-success' : '' }}">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="fw-bold">{{ $offer->buyer_name }}</div>
                        <span class="badge {{ $statusColors[$offer->status] ?? 'bg-secondary-lt' }}">
                            {{ $statusLabels[$offer->status] ?? $offer->status }}
                        </span>
                        @if($offer->financing_type)
                            <span class="badge bg-cyan-lt">
                                {{ AppModelsDealOffer::FINANCING_TYPES[$offer->financing_type] ?? $offer->financing_type }}
                            </span>
                        @endif
                    </div>
                    <div class="text-end">
                        <div class="fs-3 fw-bold text-primary">{{ Fmt::currency($offer->offer_price) }}</div>
                        @if($offer->counter_price)
                            <div class="text-muted small">العرض المقابل: {{ Fmt::currency($offer->counter_price) }}</div>
                        @endif
                    </div>
                </div>

                @if($offer->contingencies)
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach($offer->contingencies as $condition)
                            <span class="badge bg-orange-lt">
                                {{ [
                                    'financing' => 'التمويل',
                                    'property_inspection' => 'فحص العقار',
                                    'vacant_possession' => 'الإخلاء والتسليم',
                                    'inspection' => 'الفحص',
                                    'appraisal' => 'التقييم',
                                    'sale_of_home' => 'بيع عقار آخر',
                                ][$condition] ?? $condition }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @if($offer->notes)
                    <div class="text-muted small mt-2">{{ $offer->notes }}</div>
                @endif

                @if(in_array($offer->status, ['pending', 'countered'], true))
                    <div class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top">
                        <button type="button" class="btn btn-sm btn-outline-success offer-action-btn"
                                data-offer-id="{{ $offer->id }}" data-action="accepted">قبول</button>
                        <button type="button" class="btn btn-sm btn-outline-danger offer-action-btn"
                                data-offer-id="{{ $offer->id }}" data-action="rejected">رفض</button>
                        <button type="button" class="btn btn-sm btn-outline-warning offer-counter-toggle"
                                data-offer-id="{{ $offer->id }}">عرض مقابل</button>

                        <div class="offer-counter-input d-none align-items-center gap-2" id="offer-counter-{{ $offer->id }}">
                            <div class="input-group input-group-sm" style="width:190px;">
                                <input type="number" class="form-control offer-counter-price"
                                       data-offer-id="{{ $offer->id }}" step="0.01" min="0">
                                <span class="input-group-text">{{ Fmt::currencySymbol() }}</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-warning offer-counter-submit"
                                    data-offer-id="{{ $offer->id }}">إرسال</button>
                        </div>

                        <button type="button" class="btn btn-sm btn-ghost-danger ms-auto offer-delete-btn"
                                data-offer-id="{{ $offer->id }}">حذف</button>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-muted text-center py-3">لا توجد عروض مسجلة بعد.</p>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
(function() {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
    };

    var toggleBtn = document.getElementById('offers-toggle-form-btn');
    var formWrapper = document.getElementById('offers-form-wrapper');
    var cancelBtn = document.getElementById('offers-cancel-form-btn');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            formWrapper.style.display = formWrapper.style.display === 'none' ? 'block' : 'none';
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            formWrapper.style.display = 'none';
        });
    }

    document.querySelectorAll('.offer-action-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var offerId = this.getAttribute('data-offer-id');
            var action = this.getAttribute('data-action');
            this.disabled = true;

            fetch('{{ url("/offers") }}/' + offerId, {
                method: 'PATCH',
                headers: headers,
                body: JSON.stringify({ status: action })
            }).then(function(r) {
                if (!r.ok) throw new Error('update failed');
                return r.json();
            }).then(function() {
                location.reload();
            }).catch(function() {
                btn.disabled = false;
            });
        });
    });

    document.querySelectorAll('.offer-counter-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = document.getElementById('offer-counter-' + this.getAttribute('data-offer-id'));
            target.classList.toggle('d-none');
            target.classList.toggle('d-flex');
        });
    });

    document.querySelectorAll('.offer-counter-submit').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var offerId = this.getAttribute('data-offer-id');
            var input = document.querySelector('.offer-counter-price[data-offer-id="' + offerId + '"]');
            var value = parseFloat(input.value);

            if (!value || value <= 0) return;

            this.disabled = true;

            fetch('{{ url("/offers") }}/' + offerId, {
                method: 'PATCH',
                headers: headers,
                body: JSON.stringify({ status: 'countered', counter_price: value })
            }).then(function(r) {
                if (!r.ok) throw new Error('counter failed');
                return r.json();
            }).then(function() {
                location.reload();
            }).catch(function() {
                btn.disabled = false;
            });
        });
    });

    document.querySelectorAll('.offer-delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('حذف هذا العرض؟')) return;

            var offerId = this.getAttribute('data-offer-id');

            fetch('{{ url("/offers") }}/' + offerId, {
                method: 'DELETE',
                headers: headers
            }).then(function(r) {
                if (!r.ok) throw new Error('delete failed');
                return r.json();
            }).then(function() {
                location.reload();
            });
        });
    });
})();
</script>
@endpush
