@extends('layouts.app')

@section('title', 'المعاينات')
@section('page-title', 'المعاينات')

@section('content')
<div class="card" dir="rtl">
    <div class="card-header">
        <h3 class="card-title">المعاينات</h3>
        <div class="card-actions">
            <a href="{{ route('showings.create') }}" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                جدولة معاينة
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card-body border-bottom py-3">
        <form method="GET" action="{{ route('showings.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">الحالة</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    @foreach(\App\Models\Showing::STATUSES as $key => $label)
                        <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">من</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">إلى</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            @if(auth()->user()->isAdmin() && $agents->isNotEmpty())
            <div class="col-md-2">
                <label class="form-label">الوسيط</label>
                <select name="agent" class="form-select form-select-sm">
                    <option value="">كل الوسطاء</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" {{ request('agent') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-2">
                <label class="form-label">بحث</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="العقار أو العميل...">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">تصفية</button>
                <a href="{{ route('showings.index') }}" class="btn btn-sm btn-outline-secondary">مسح</a>
            </div>
        </form>
    </div>
    <x-saved-views-bar entity-type="showings" />

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>العقار</th>
                    <th>العميل</th>
                    <th>التاريخ والوقت</th>
                    <th>{{ __('Agent') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>النتيجة</th>
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($showings as $showing)
                <tr>
                    <td>
                        @if($showing->property)
                            <a href="{{ route('properties.show', $showing->property) }}">{{ $showing->property->address }}</a>
                            <div class="text-muted small">{{ $showing->property->district ? $showing->property->district.'، ' : '' }}{{ $showing->property->city }}</div>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if($showing->lead)
                            <a href="{{ route('leads.show', $showing->lead) }}">{{ $showing->lead->first_name }} {{ $showing->lead->last_name }}</a>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        <div>{{ $showing->showing_date->format('M j, Y') }}</div>
                        <div class="text-muted small">{{ \Carbon\Carbon::parse($showing->showing_time)->format('H:i') }} ({{ $showing->duration_minutes }} دقيقة)</div>
                    </td>
                    <td>{{ $showing->agent->name ?? '-' }}</td>
                    <td>
                        @php
                            $statusColors = ['scheduled' => 'blue', 'completed' => 'green', 'cancelled' => 'secondary', 'no_show' => 'red'];
                        @endphp
                        <span class="badge bg-{{ $statusColors[$showing->status] ?? 'secondary' }}">{{ \App\Models\Showing::statusLabel($showing->status) }}</span>
                    </td>
                    <td>
                        @if($showing->outcome)
                            @php
                                $outcomeColors = ['interested' => 'green', 'not_interested' => 'secondary', 'made_offer' => 'purple', 'needs_second_showing' => 'yellow'];
                            @endphp
                            <span class="badge bg-{{ $outcomeColors[$showing->outcome] ?? 'secondary' }}">{{ \App\Models\Showing::outcomeLabel($showing->outcome) }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('showings.show', $showing) }}" class="btn btn-sm btn-outline-primary">فتح</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">لا توجد معاينات.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showings->hasPages())
    <div class="card-footer d-flex align-items-center">
        {{ $showings->appends(request()->query())->links('vendor.pagination.tabler') }}
    </div>
    @endif
</div>
@endsection
