@extends('layouts.app')

@section('title', 'إضافة عقار')
@section('page-title', 'إضافة عقار')

@section('content')
<div class="card" dir="rtl">
    <div class="card-header">
        <div>
            <h3 class="card-title">عقار جديد</h3>
            <div class="text-muted small mt-1">احفظ العقار مرة واحدة، وسيبحث النظام تلقائيًا عن طلبات العملاء المناسبة.</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('properties.manage.store') }}">
            @csrf
            @include('properties._saudi_form')
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary">حفظ وتشغيل المطابقة</button>
                <a href="{{ route('properties.index') }}" class="btn btn-outline-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
