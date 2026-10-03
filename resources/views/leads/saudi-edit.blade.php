@extends('layouts.app')

@section('title', 'تعديل العميل')
@section('page-title', 'تعديل العميل')

@section('content')
<div class="card" dir="rtl">
    <div class="card-header">
        <div>
            <h3 class="card-title">تعديل {{ $lead->full_name }}</h3>
            <div class="text-muted small mt-1">حدّث بيانات العميل وحالة المتابعة والوسيط المسؤول.</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('leads.update', $lead) }}">
            @csrf
            @method('PUT')
            @include('leads._saudi_form')
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary">حفظ التعديلات</button>
                <a href="{{ route('leads.show', $lead) }}" class="btn btn-outline-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
