<div class="card mb-3" dir="rtl">
    <div class="card-header">
        <div>
            <h3 class="card-title">العمولة</h3>
            <div class="text-muted small mt-1">سجل إجمالي العمولة، ثم وزعها بين المكتب والوسيط.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label">قيمة الصفقة</label>
            <div class="input-group input-group-sm">
                <input type="number" class="form-control" id="calc-sale-price" step="0.01" min="0" value="{{ $deal->contract_price ?? '' }}">
                <span class="input-group-text">{{ Fmt::currencySymbol() }}</span>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">إجمالي العمولة</label>
            <div class="input-group input-group-sm">
                <input type="number" class="form-control" id="calc-total-commission" step="0.01" min="0" value="{{ $deal->total_commission ?? '' }}">
                <span class="input-group-text">{{ Fmt::currencySymbol() }}</span>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">حصة المكتب من العمولة (%)</label>
            <input type="number" class="form-control form-control-sm" id="calc-office-split" step="0.01" min="0" max="100" value="{{ $deal->brokerage_split_pct ?? 0 }}">
        </div>

        <div class="mb-3">
            <label class="form-label">حالة العمولة</label>
            <select id="calc-commission-status" class="form-select form-select-sm">
                <option value="pending" @selected(($deal->commission_status ?? 'pending') === 'pending')>معلقة</option>
                <option value="due" @selected(($deal->commission_status ?? 'pending') === 'due')>مستحقة</option>
                <option value="paid" @selected(($deal->commission_status ?? 'pending') === 'paid')>مدفوعة</option>
            </select>
            @if($deal->commission_paid_at)
                <div class="form-hint">تم تسجيل الدفع: {{ $deal->commission_paid_at->format('Y-m-d H:i') }}</div>
            @endif
        </div>

        <hr>

        <div class="mb-2 d-flex justify-content-between">
            <span class="text-secondary">حصة المكتب:</span>
            <strong id="calc-office-amount">—</strong>
        </div>
        <div class="mb-3 d-flex justify-content-between">
            <span class="text-secondary">حصة الوسيط:</span>
            <strong class="text-green fs-4" id="calc-agent-amount">—</strong>
        </div>

        <button type="button" class="btn btn-primary w-100 btn-sm" id="calc-save-btn">حفظ العمولة</button>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var priceInput = document.getElementById('calc-sale-price');
    var commissionInput = document.getElementById('calc-total-commission');
    var officeSplitInput = document.getElementById('calc-office-split');
    var statusInput = document.getElementById('calc-commission-status');
    var officeAmount = document.getElementById('calc-office-amount');
    var agentAmount = document.getElementById('calc-agent-amount');
    var saveBtn = document.getElementById('calc-save-btn');

    var jsLocale = '{{ Fmt::jsLocale() }}';
    var currencyCode = '{{ Fmt::currencyCode() }}';

    function formatCurrency(value) {
        try {
            return new Intl.NumberFormat(jsLocale, {
                style: 'currency',
                currency: currencyCode,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        } catch (e) {
            return value.toFixed(2) + ' ' + currencyCode;
        }
    }

    function recalc() {
        var total = parseFloat(commissionInput.value) || 0;
        var officePct = Math.min(100, Math.max(0, parseFloat(officeSplitInput.value) || 0));
        var office = total * officePct / 100;
        var agent = total - office;

        officeAmount.textContent = formatCurrency(office);
        agentAmount.textContent = formatCurrency(agent);
    }

    commissionInput.addEventListener('input', recalc);
    officeSplitInput.addEventListener('input', recalc);
    recalc();

    saveBtn.addEventListener('click', function() {
        saveBtn.disabled = true;
        saveBtn.textContent = 'جارٍ الحفظ...';

        fetch('{{ url("/pipeline/" . $deal->id) }}', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                contract_price: parseFloat(priceInput.value) || 0,
                total_commission: parseFloat(commissionInput.value) || 0,
                brokerage_split_pct: parseFloat(officeSplitInput.value) || 0,
                commission_status: statusInput.value
            })
        })
        .then(function(response) {
            if (!response.ok) throw new Error('save failed');
            return response.json();
        })
        .then(function(data) {
            if (!data.success) throw new Error('save failed');
            saveBtn.textContent = 'تم الحفظ';
            saveBtn.classList.remove('btn-primary');
            saveBtn.classList.add('btn-success');

            setTimeout(function() {
                saveBtn.textContent = 'حفظ العمولة';
                saveBtn.classList.remove('btn-success');
                saveBtn.classList.add('btn-primary');
                saveBtn.disabled = false;
            }, 1200);
        })
        .catch(function() {
            saveBtn.textContent = 'تعذر الحفظ';
            saveBtn.disabled = false;
        });
    });
});
</script>
@endpush
