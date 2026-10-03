@extends('layouts.app')

@section('title', 'العملاء')
@section('page-title', 'العملاء')

@section('content')
@php
    $sources = \App\Services\CustomFieldService::getOptions('lead_source');
    $statuses = \App\Services\CustomFieldService::getOptions('lead_status');
    $contactTypes = \App\Services\BusinessModeService::getRealEstateContactTypes();
    $temperatures = \App\Services\BusinessModeService::getRealEstateTemperatures();
@endphp

<div class="card" dir="rtl">
    <div class="card-header">
        <div>
            <h3 class="card-title">عملاء المكتب</h3>
            <div class="text-muted small mt-1">الملاك والباحثون عن عقارات والعملاء النشطون.</div>
        </div>
        <div class="card-actions">
            <a href="{{ route('leads.create') }}" class="btn btn-primary">إضافة عميل</a>
        </div>
    </div>

    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">بحث</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       value="{{ request('search') }}" placeholder="الاسم أو الجوال أو البريد">
            </div>
            <div class="col-md-2">
                <label class="form-label">نوع العميل</label>
                <select name="contact_type" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    @foreach($contactTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('contact_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">الحالة</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">المصدر</label>
                <select name="source" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    @foreach($sources as $value => $label)
                        <option value="{{ $value }}" @selected(request('source') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if(auth()->user()->isAdmin())
                <div class="col-md-2">
                    <label class="form-label">الوسيط</label>
                    <select name="agent_id" class="form-select form-select-sm">
                        <option value="">الكل</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" @selected((string) request('agent_id') === (string) $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">تصفية</button>
                <a href="{{ route('leads.index') }}" class="btn btn-sm btn-outline-secondary">مسح</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>العميل</th>
                    <th>التواصل</th>
                    <th>النوع</th>
                    <th>المصدر</th>
                    <th>الحالة</th>
                    <th>الأولوية</th>
                    <th>الوسيط</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($leads as $lead)
                <tr>
                    <td>
                        <a href="{{ route('leads.show', $lead) }}" class="fw-bold">{{ $lead->full_name }}</a>
                        @if($lead->do_not_contact)
                            <span class="badge bg-red-lt">عدم تواصل</span>
                        @endif
                    </td>
                    <td>
                        @if($lead->phone)<div>{{ $lead->phone }}</div>@endif
                        @if($lead->email)<div class="text-muted small">{{ $lead->email }}</div>@endif
                    </td>
                    <td>{{ $contactTypes[$lead->contact_type] ?? 'غير محدد' }}</td>
                    <td>{{ \App\Services\BusinessModeService::getLeadSourceLabel($lead->lead_source, auth()->user()->tenant) }}</td>
                    <td><span class="badge bg-green-lt">{{ $statuses[$lead->status] ?? $lead->status }}</span></td>
                    <td>{{ $temperatures[$lead->temperature] ?? $lead->temperature }}</td>
                    <td>{{ $lead->agent?->name ?? '—' }}</td>
                    <td><a href="{{ route('leads.show', $lead) }}" class="btn btn-sm btn-outline-primary">فتح</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-5">لا يوجد عملاء بعد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($leads->hasPages())
        <div class="card-footer">{{ $leads->links() }}</div>
    @endif
</div>
@endsection
