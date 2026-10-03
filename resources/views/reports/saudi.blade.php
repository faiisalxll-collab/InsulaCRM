@extends('layouts.app')

@section('title', 'التقارير')
@section('page-title', 'التقارير')

@section('content')
@php
    $propertyTypes = \App\Services\CustomFieldService::getOptions('property_type');
@endphp

<div dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-1">تقارير المكتب العقاري</h2>
            <div class="text-muted">ملخص الحركة من العميل والعقار حتى الإغلاق والعمولة.</div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">من</label>
                    <input type="date" name="from" class="form-control" value="{{ $from }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">إلى</label>
                    <input type="date" name="to" class="form-control" value="{{ $to }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الوسيط</label>
                    <select name="agent_id" class="form-select">
                        <option value="">كل الوسطاء</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" @selected((string) $agentId === (string) $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100">تطبيق</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-6 col-md-4 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">عملاء جدد</div>
                    <div class="fs-1 fw-bold">{{ number_format($newClients) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">عقارات مضافة</div>
                    <div class="fs-1 fw-bold">{{ number_format($newProperties) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">طلبات جديدة</div>
                    <div class="fs-1 fw-bold">{{ number_format($newRequests) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">مطابقات مؤهلة</div>
                    <div class="fs-1 fw-bold">{{ number_format($eligibleMatches) }}</div>
                    <div class="text-muted small">متوسط {{ $avgMatchScore }}%</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">المعاينات</div>
                    <div class="fs-1 fw-bold">{{ number_format($showingsCount) }}</div>
                    <div class="text-muted small">{{ number_format($completedShowings) }} مكتملة</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">العروض</div>
                    <div class="fs-1 fw-bold">{{ number_format($offersCount) }}</div>
                    <div class="text-muted small">{{ number_format($acceptedOffers) }} مقبولة</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">صفقات مغلقة</div>
                    <div class="fs-1 fw-bold">{{ number_format($closedDeals) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">حجم الإغلاقات</div>
                    <div class="fs-2 fw-bold">{{ Fmt::currency($closedVolume) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">عمولات مولدة</div>
                    <div class="fs-2 fw-bold">{{ Fmt::currency($commissionsGenerated) }}</div>
                    <div class="text-warning small">مستحقة: {{ Fmt::currency($commissionsDue) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">عمولات مدفوعة</div>
                    <div class="fs-2 fw-bold text-green">{{ Fmt::currency($commissionsPaid) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">بيع وإيجار</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>العملية</th><th>العدد</th></tr></thead>
                        <tbody>
                        @forelse($transactionBreakdown as $row)
                            <tr>
                                <td>{{ $row->transaction_type === 'rent' ? 'إيجار' : 'بيع' }}</td>
                                <td>{{ number_format($row->count) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted">لا توجد بيانات.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">أنواع العقارات</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>النوع</th><th>العدد</th></tr></thead>
                        <tbody>
                        @forelse($propertyTypeBreakdown as $row)
                            <tr>
                                <td>{{ $propertyTypes[$row->property_type] ?? $row->property_type }}</td>
                                <td>{{ number_format($row->count) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted">لا توجد بيانات.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">أكثر الأحياء نشاطًا</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>الحي</th><th>العقارات</th></tr></thead>
                        <tbody>
                        @forelse($districtBreakdown as $row)
                            <tr>
                                <td>{{ $row->district }}</td>
                                <td>{{ number_format($row->count) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted">لا توجد بيانات.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">نتائج المعاينات</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>النتيجة</th><th>العدد</th></tr></thead>
                        <tbody>
                        @forelse($showingOutcomes as $row)
                            <tr>
                                <td>{{ \App\Models\Showing::OUTCOMES[$row->outcome] ?? $row->outcome }}</td>
                                <td>{{ number_format($row->count) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted">لا توجد نتائج معاينات في الفترة.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">الصفقات المفتوحة حسب المرحلة</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>المرحلة</th><th>العدد</th></tr></thead>
                        <tbody>
                        @forelse($dealStages as $row)
                            <tr>
                                <td>{{ \App\Models\Deal::stageLabel($row->stage) }}</td>
                                <td>{{ number_format($row->count) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted">لا توجد صفقات مفتوحة.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if(!$agentId)
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">أداء الوسطاء</h3>
                <div class="text-muted small mt-1">الصفقات المغلقة وحجمها والعمولات في الفترة المحددة.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>الوسيط</th>
                        <th>صفقات مغلقة</th>
                        <th>حجم الإغلاقات</th>
                        <th>العمولات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($agentPerformance as $row)
                    <tr>
                        <td>{{ $row->agent?->name ?? '—' }}</td>
                        <td>{{ number_format($row->closed_count) }}</td>
                        <td>{{ Fmt::currency($row->closed_volume) }}</td>
                        <td>{{ Fmt::currency($row->commissions) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">لا توجد صفقات مغلقة في الفترة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
