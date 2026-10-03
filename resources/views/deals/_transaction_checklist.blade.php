@php
    $checklistItems = $deal->checklistItems ?? collect();
    $completedCount = $checklistItems->whereIn('status', ['completed', 'waived'])->count();
    $totalCount = $checklistItems->count();
    $progressPct = $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 0;
@endphp

<div class="card mb-3" id="checklist-card" dir="rtl">
    <div class="card-header">
        <div>
            <h3 class="card-title">خطوات الإغلاق</h3>
            <div class="text-muted small mt-1">قائمة تنفيذ الصفقة من الاتفاق حتى التسليم وحفظ المستندات.</div>
        </div>
        @if($totalCount > 0)
            <div class="card-actions">
                <span class="badge bg-{{ $completedCount === $totalCount ? 'green' : 'blue' }}-lt" id="checklist-progress-badge">
                    {{ $completedCount }}/{{ $totalCount }} مكتمل
                </span>
            </div>
        @endif
    </div>

    <div class="card-body">
        @if($totalCount === 0)
            <div class="text-center py-3">
                <p class="text-muted mb-3">لم تُنشأ خطوات الإغلاق لهذه الصفقة بعد.</p>
                <form method="POST" action="{{ route('deals.storeChecklist', $deal) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">إنشاء خطوات الإغلاق</button>
                </form>
            </div>
        @else
            <div class="progress mb-3">
                <div class="progress-bar bg-green" style="width: {{ $progressPct }}%" role="progressbar"
                     aria-valuenow="{{ $progressPct }}" aria-valuemin="0" aria-valuemax="100">
                    {{ $progressPct }}%
                </div>
            </div>

            <div id="checklist-items">
                @foreach($checklistItems as $item)
                    @php
                        $statusColors = [
                            'pending' => 'bg-blue',
                            'in_progress' => 'bg-yellow',
                            'completed' => 'bg-green',
                            'waived' => 'bg-secondary',
                            'failed' => 'bg-red',
                        ];
                        $statusColor = $statusColors[$item->status] ?? 'bg-secondary';
                        $daysUntil = $item->deadline ? now()->startOfDay()->diffInDays($item->deadline, false) : null;
                    @endphp

                    <div class="checklist-row border rounded p-3 mb-2" data-item-id="{{ $item->id }}">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center flex-grow-1">
                                <span class="status-dot d-block {{ $statusColor }} ms-2"></span>
                                <span class="fw-medium {{ $item->status === 'completed' ? 'text-decoration-line-through text-muted' : '' }}">
                                    {{ $item->label }}
                                </span>
                                @if($item->deadline)
                                    <span class="badge bg-{{ $item->is_overdue ? 'red' : (($daysUntil !== null && $daysUntil <= 3 && $daysUntil >= 0) ? 'yellow' : 'secondary') }}-lt me-2">
                                        {{ $item->deadline->format('Y-m-d') }}
                                        @if($item->is_overdue) · متأخر @endif
                                    </span>
                                @endif
                            </div>

                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <select class="form-select form-select-sm checklist-status-select"
                                        data-item-id="{{ $item->id }}" style="width:auto; min-width:130px;">
                                    @foreach(AppModelsTransactionChecklist::STATUSES as $statusKey => $statusLabel)
                                        <option value="{{ $statusKey }}" @selected($item->status === $statusKey)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>

                                <input type="date" class="form-control form-control-sm checklist-deadline-input"
                                       data-item-id="{{ $item->id }}"
                                       value="{{ $item->deadline?->format('Y-m-d') }}"
                                       style="width:145px;">

                                <button type="button" class="btn btn-sm btn-outline-secondary checklist-notes-toggle"
                                        data-item-id="{{ $item->id }}">ملاحظات</button>

                                <button type="button" class="btn btn-sm btn-outline-danger checklist-delete-btn"
                                        data-item-id="{{ $item->id }}">حذف</button>
                            </div>
                        </div>

                        <div class="checklist-notes mt-2" id="checklist-notes-{{ $item->id }}" style="display:none;">
                            <textarea class="form-control form-control-sm checklist-notes-input"
                                      data-item-id="{{ $item->id }}" rows="2"
                                      placeholder="أضف ملاحظات لهذه الخطوة">{{ $item->notes }}</textarea>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2 checklist-notes-save"
                                    data-item-id="{{ $item->id }}">حفظ الملاحظات</button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border rounded p-3 mt-3 bg-light">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <input type="text" class="form-control form-control-sm flex-grow-1"
                           id="checklist-new-label" placeholder="أضف خطوة إغلاق مخصصة" maxlength="255">
                    <input type="date" class="form-control form-control-sm"
                           id="checklist-new-deadline" style="width:150px;">
                    <button type="button" class="btn btn-sm btn-primary" id="checklist-add-btn">إضافة</button>
                </div>
            </div>
        @endif
    </div>
</div>

@if($totalCount > 0)
@push('scripts')
<script>
(function() {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' };

    document.querySelectorAll('.checklist-status-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var itemId = this.getAttribute('data-item-id');
            fetch('{{ url("/checklist") }}/' + itemId, {
                method: 'PATCH',
                headers: headers,
                body: JSON.stringify({ status: this.value })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
            });
        });
    });

    document.querySelectorAll('.checklist-deadline-input').forEach(function(input) {
        input.addEventListener('change', function() {
            var itemId = this.getAttribute('data-item-id');
            fetch('{{ url("/checklist") }}/' + itemId, {
                method: 'PATCH',
                headers: headers,
                body: JSON.stringify({ deadline: this.value || null })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
            });
        });
    });

    document.querySelectorAll('.checklist-notes-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var notes = document.getElementById('checklist-notes-' + this.getAttribute('data-item-id'));
            notes.style.display = notes.style.display === 'none' ? 'block' : 'none';
        });
    });

    document.querySelectorAll('.checklist-notes-save').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var itemId = this.getAttribute('data-item-id');
            var textarea = document.querySelector('.checklist-notes-input[data-item-id="' + itemId + '"]');
            var saveBtn = this;
            saveBtn.disabled = true;

            fetch('{{ url("/checklist") }}/' + itemId, {
                method: 'PATCH',
                headers: headers,
                body: JSON.stringify({ notes: textarea.value })
            }).then(function(r) { return r.json(); }).then(function(data) {
                saveBtn.disabled = false;
                if (data.success) saveBtn.textContent = 'تم الحفظ';
                setTimeout(function() { saveBtn.textContent = 'حفظ الملاحظات'; }, 1200);
            }).catch(function() {
                saveBtn.disabled = false;
            });
        });
    });

    document.querySelectorAll('.checklist-delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('حذف هذه الخطوة؟')) return;

            var itemId = this.getAttribute('data-item-id');
            fetch('{{ url("/checklist") }}/' + itemId, {
                method: 'DELETE',
                headers: headers
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
            });
        });
    });

    var addBtn = document.getElementById('checklist-add-btn');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            var labelInput = document.getElementById('checklist-new-label');
            var deadlineInput = document.getElementById('checklist-new-deadline');
            var label = labelInput.value.trim();
            if (!label) return;

            addBtn.disabled = true;
            fetch('{{ url("/pipeline/" . $deal->id . "/checklist/add") }}', {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({ label: label, deadline: deadlineInput.value || null })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
                addBtn.disabled = false;
            }).catch(function() {
                addBtn.disabled = false;
            });
        });
    }
})();
</script>
@endpush
@endif
