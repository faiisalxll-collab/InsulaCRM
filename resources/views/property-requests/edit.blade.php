@extends('layouts.app')

@section('title', 'تعديل الطلب العقاري')
@section('page-title', 'تعديل الطلب العقاري')

@section('content')
<div dir="rtl" class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">تعديل الطلب #{{ $propertyRequest->id }}</h3>
            <div class="text-muted small mt-1">تغيير معايير المطابقة يعيد حساب النتائج تلقائيًا.</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('property-requests.update', $propertyRequest) }}">
            @csrf
            @method('PUT')
            @include('property-requests._form')
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary">حفظ التعديلات</button>
                <a class="btn btn-outline-secondary" href="{{ route('property-requests.show', $propertyRequest) }}">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
