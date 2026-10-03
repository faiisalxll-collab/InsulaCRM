@extends('layouts.app')

@section('title', 'طلب عقاري جديد')
@section('page-title', 'طلب عقاري جديد')

@section('content')
<div dir="rtl" class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">إضافة طلب عميل</h3>
            <div class="text-muted small mt-1">بعد الحفظ سيبحث النظام تلقائيًا عن العقارات النشطة المطابقة.</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('property-requests.store') }}">
            @csrf
            @include('property-requests._form')
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary">حفظ وتشغيل المطابقة</button>
                <a class="btn btn-outline-secondary" href="{{ route('property-requests.index') }}">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
