@extends('layouts.app')

@section('title', 'إضافة عميل')
@section('page-title', 'إضافة عميل')

@section('content')
<div class="card" dir="rtl">
    <div class="card-header">
        <div>
            <h3 class="card-title">عميل جديد</h3>
            <div class="text-muted small mt-1">أدخل بيانات التواصل، ثم أضف له عقارًا أو طلب بحث حسب احتياجه.</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('leads.store') }}">
            @csrf
            @include('leads._saudi_form')
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary">حفظ العميل</button>
                <a href="{{ route('leads.index') }}" class="btn btn-outline-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
