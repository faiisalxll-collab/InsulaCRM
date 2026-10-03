@extends('layouts.app')

@section('title', 'تعديل العقار')
@section('page-title', 'تعديل العقار')

@section('content')
<div class="card" dir="rtl">
    <div class="card-header">
        <div>
            <h3 class="card-title">تعديل العقار #{{ $property->id }}</h3>
            <div class="text-muted small mt-1">أي تغيير في السعر أو الموقع أو المواصفات يعيد حساب المطابقات تلقائيًا.</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('properties.update', $property) }}">
            @csrf
            @method('PUT')
            @include('properties._saudi_form')
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary">حفظ التعديلات</button>
                <a href="{{ route('properties.show', $property) }}" class="btn btn-outline-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
