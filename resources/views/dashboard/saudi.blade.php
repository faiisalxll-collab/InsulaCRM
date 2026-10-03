@extends('layouts.app')

@section('title', 'الرئيسية')
@section('page-title', 'الرئيسية')

@section('content')
<div dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-1">لوحة المكتب العقاري</h2>
            <div class="text-muted">ما يحتاج انتباهك اليوم، بدون دفن الأرقام تحت عشرين رسمًا لا يفتحها أحد.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('properties.create') }}" class="btn btn-primary">إضافة عقار</a>
            <a href="{{ route('property-requests.create') }}" class="btn btn-outline-primary">إضافة طلب</a>
        </div>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-6 col-md-4 col-xl">
            <a href="{{ route('properties.index', ['listing_status' => 'active']) }}" class="card card-link">
                <div class="card-body">
                    <div class="text-muted small">العقارات النشطة</div>
                    <div class="fs-1 fw-bold">{{ number_format($activeProperties) }}</div>
                </div>
            </a>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <a href="{{ route('property-requests.index', ['status' => 'active']) }}" class="card card-link">
                <div class="card-body">
                    <div class="text-muted small">الطلبات النشطة</div>
                    <div class="fs-1 fw-bold">{{ number_format($activeRequests) }}</div>
                </div>
            </a>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <a href="{{ route('property-matches.index') }}" class="card card-link">
                <div class="card-body">
                    <div class="text-muted small">المطابقات الحالية</div>
                    <div class="fs-1 fw-bold">{{ number_format($eligibleMatches) }}</div>
                </div>
            </a>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <a href="{{ route('showings.index') }}" class="card card-link">
                <div class="card-body">
                    <div class="text-muted small">معاينات اليوم</div>
                    <div class="fs-1 fw-bold">{{ number_format($todayShowings) }}</div>
                </div>
            </a>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <a href="{{ route('pipeline') }}" class="card card-link">
                <div class="card-body">
                    <div class="text-muted small">الصفقات المفتوحة</div>
                    <div class="fs-1 fw-bold">{{ number_format($activeDeals) }}</div>
                </div>
            </a>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">مغلقة هذا الشهر</div>
                    <div class="fs-1 fw-bold">{{ number_format($closedThisMonth) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-muted small">عمولات مستحقة</div>
                <div class="fs-2 fw-bold">{{ Fmt::currency($commissionDue) }}</div>
            </div>
            <a href="{{ route('pipeline') }}" class="btn btn-outline-primary">فتح الصفقات</a>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">أفضل المطابقات الآن</h3>
                        <div class="text-muted small mt-1">طلبات جاهزة للتحريك بدل انتظار العميل أن يتصل مرة ثانية.</div>
                    </div>
                    <div class="card-actions">
                        <a href="{{ route('property-matches.index') }}" class="btn btn-sm btn-outline-primary">كل المطابقات</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>النسبة</th>
                                <th>العميل</th>
                                <th>العقار</th>
                                <th>السعر</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($topMatches as $match)
                            <tr>
                                <td>
                                    <span class="badge bg-{{ $match->match_score >= 85 ? 'green' : ($match->match_score >= 70 ? 'blue' : 'yellow') }}-lt">
                                        {{ $match->match_score }}%
                                    </span>
                                </td>
                                <td>{{ $match->request?->lead?->full_name ?? 'طلب #'.$match->property_request_id }}</td>
                                <td>
                                    <strong>{{ $match->property?->address ?? '—' }}</strong>
                                    <div class="text-muted small">
                                        {{ $match->property?->district ? $match->property->district.'، ' : '' }}{{ $match->property?->city }}
                                    </div>
                                </td>
                                <td>
                                    @if($match->property)
                                        {{ Fmt::currency($match->property->list_price ?? $match->property->asking_price) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($match->request)
                                        <a href="{{ route('property-requests.show', $match->request) }}" class="btn btn-sm btn-outline-primary">فتح</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-5">لا توجد مطابقات نشطة حاليًا.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">المعاينات القادمة</h3>
                        <div class="text-muted small mt-1">أقرب المواعيد المسجلة.</div>
                    </div>
                    <div class="card-actions">
                        <a href="{{ route('showings.index') }}" class="btn btn-sm btn-outline-primary">المعاينات</a>
                    </div>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($upcomingShowings as $showing)
                        <a href="{{ route('showings.show', $showing) }}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <strong>{{ $showing->property?->address ?? 'عقار' }}</strong>
                                    <div class="text-muted small">
                                        {{ $showing->propertyRequest?->lead?->full_name ?? $showing->lead?->full_name ?? 'بدون عميل' }}
                                    </div>
                                </div>
                                <div class="text-end small">
                                    <div>{{ $showing->showing_date?->format('Y-m-d') }}</div>
                                    <div class="text-muted">{{ $showing->showing_time }}</div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="text-center text-muted py-5">لا توجد معاينات قادمة.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h3 class="card-title">الصفقات الجارية</h3>
                <div class="text-muted small mt-1">آخر الصفقات التي لم تُغلق بعد.</div>
            </div>
            <div class="card-actions">
                <a href="{{ route('pipeline') }}" class="btn btn-sm btn-outline-primary">كل الصفقات</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>العقار</th>
                        <th>العميل</th>
                        <th>المرحلة</th>
                        <th>قيمة الاتفاق</th>
                        <th>الوسيط</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($openDeals as $deal)
                    <tr>
                        <td>
                            @if($deal->property)
                                <a href="{{ route('properties.show', $deal->property) }}">{{ $deal->property->address }}</a>
                            @else
                                {{ $deal->title }}
                            @endif
                        </td>
                        <td>{{ $deal->propertyRequest?->lead?->full_name ?? '—' }}</td>
                        <td><span class="badge bg-blue-lt">{{ \App\Models\Deal::stageLabel($deal->stage) }}</span></td>
                        <td>{{ $deal->contract_price !== null ? Fmt::currency($deal->contract_price) : '—' }}</td>
                        <td>{{ $deal->agent?->name ?? '—' }}</td>
                        <td><a href="{{ route('deals.show', $deal) }}" class="btn btn-sm btn-outline-primary">فتح</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">لا توجد صفقات مفتوحة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
