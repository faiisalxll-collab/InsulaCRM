@extends('layouts.app')

@section('title', 'تفاصيل المعاينة')
@section('page-title', 'تفاصيل المعاينة')

@section('content')
<div class="row" dir="rtl">
    <div class="col-md-8">
        {{-- Showing Details --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">بيانات المعاينة</h3>
                <div class="card-actions d-flex gap-2">
                    @if(($businessMode ?? 'wholesale') === 'realestate' && $showing->propertyRequest && $showing->property)
                        @if($showing->deal)
                            <a href="{{ route('deals.show', $showing->deal) }}" class="btn btn-sm btn-success">فتح الصفقة</a>
                        @else
                            <form method="POST" action="{{ route('showings.startDeal', $showing) }}">
                                @csrf
                                <button class="btn btn-sm btn-primary">بدء التفاوض</button>
                            </form>
                        @endif
                    @endif
                    <a href="{{ route('showings.edit', $showing) }}" class="btn btn-sm btn-outline-primary">تعديل</a>
                    <form method="POST" action="{{ route('showings.destroy', $showing) }}" class="d-inline" onsubmit="return confirm('حذف هذه المعاينة؟')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">العقار</div>
                        <div class="datagrid-content">
                            @if($showing->property)
                                <a href="{{ route('properties.show', $showing->property) }}">{{ $showing->property->address }}</a>
                                <div class="text-muted small">{{ $showing->property->district ? $showing->property->district.'، ' : '' }}{{ $showing->property->city }}</div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">العميل</div>
                        <div class="datagrid-content">
                            @if($showing->lead)
                                <a href="{{ route('leads.show', $showing->lead) }}">{{ $showing->lead->first_name }} {{ $showing->lead->last_name }}</a>
                            @else
                                <span class="text-muted">غير مرتبط</span>
                            @endif
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">التاريخ والوقت</div>
                        <div class="datagrid-content">
                            {{ $showing->showing_date->format('Y-m-d') }} — {{ \Carbon\Carbon::parse($showing->showing_time)->format('H:i') }}
                            <div class="text-muted small">{{ $showing->duration_minutes }} دقيقة</div>
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">الوسيط</div>
                        <div class="datagrid-content">{{ $showing->agent->name ?? '-' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">الحالة</div>
                        <div class="datagrid-content">
                            @php
                                $statusColors = ['scheduled' => 'blue', 'completed' => 'green', 'cancelled' => 'secondary', 'no_show' => 'red'];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$showing->status] ?? 'secondary' }}">{{ \App\Models\Showing::statusLabel($showing->status) }}</span>
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">النتيجة</div>
                        <div class="datagrid-content">
                            @if($showing->outcome)
                                @php
                                    $outcomeColors = ['interested' => 'green', 'not_interested' => 'secondary', 'made_offer' => 'purple', 'needs_second_showing' => 'yellow'];
                                @endphp
                                <span class="badge bg-{{ $outcomeColors[$showing->outcome] ?? 'secondary' }}">{{ \App\Models\Showing::outcomeLabel($showing->outcome) }}</span>
                            @else
                                <span class="text-muted">لم تسجل</span>
                            @endif
                        </div>
                    </div>
                    @if($showing->listing_agent_name)
                    <div class="datagrid-item">
                        <div class="datagrid-title">وسيط الطرف الآخر</div>
                        <div class="datagrid-content">
                            {{ $showing->listing_agent_name }}
                            @if($showing->listing_agent_phone)
                                <div class="text-muted small">{{ $showing->listing_agent_phone }}</div>
                            @endif
                        </div>
                    </div>
                    @endif
                    @if($showing->deal)
                    <div class="datagrid-item">
                        <div class="datagrid-title">الصفقة</div>
                        <div class="datagrid-content">
                            <a href="{{ url('/pipeline/' . $showing->deal_id) }}">{{ $showing->deal->title }}</a>
                        </div>
                    </div>
                    @endif
                </div>

                @if($showing->notes)
                <div class="mt-3">
                    <h4 class="subheader">ملاحظات</h4>
                    <p>{{ $showing->notes }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Feedback Card --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">نتيجة المعاينة</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('showings.update', $showing) }}">
                    @csrf @method('PUT')

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select name="status" class="form-select">
                                @foreach(\App\Models\Showing::STATUSES as $key => $label)
                                    <option value="{{ $key }}" {{ $showing->status === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Outcome') }}</label>
                            <select name="outcome" class="form-select">
                                <option value="">اختر النتيجة</option>
                                @foreach(\App\Models\Showing::OUTCOMES as $key => $label)
                                    <option value="{{ $key }}" {{ $showing->outcome === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ملاحظات العميل</label>
                        <textarea name="feedback" class="form-control" rows="4" placeholder="ماذا حدث في المعاينة؟ وما رأي العميل؟">{{ $showing->feedback }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">حفظ النتيجة</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        {{-- Property Quick View --}}
        @if($showing->property)
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">العقار</h3>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <strong>{{ $showing->property->address }}</strong>
                    <div class="text-muted">{{ $showing->property->city }}, {{ $showing->property->state }} {{ $showing->property->zip_code }}</div>
                </div>
                @if($showing->property->list_price)
                <div class="mb-2">
                    <span class="text-muted">السعر:</span>
                    <strong>{{ Fmt::currency($showing->property->list_price) }}</strong>
                </div>
                @endif
                @if($showing->property->bedrooms || $showing->property->bathrooms)
                <div class="mb-2">
                    @if($showing->property->bedrooms)<span>{{ $showing->property->bedrooms }} غرف</span>@endif
                    @if($showing->property->bedrooms && $showing->property->bathrooms) / @endif
                    @if($showing->property->bathrooms)<span>{{ $showing->property->bathrooms }} دورات مياه</span>@endif
                </div>
                @endif
                @if($showing->property->square_footage)
                <div class="mb-2">{{ Fmt::area($showing->property->square_footage) }}</div>
                @endif
                <a href="{{ route('properties.show', $showing->property) }}" class="btn btn-sm btn-outline-primary w-100">فتح العقار</a>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
