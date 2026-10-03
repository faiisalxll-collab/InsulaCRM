@extends('layouts.app')
@section('title', __('WhatsApp'))
@section('content')
<div class="container-xl">
    <div class="page-header"><div class="page-header d-print-none"><h2 class="page-title">واتساب المكتب</h2></div></div>
    <div class="card mt-3">
        <div class="card-body">
            @if($account && $account->status === 'connected')
                <div class="alert alert-success">متصل: <strong>{{ $account->display_phone_number ?: 'WhatsApp Business' }}</strong></div>
                <p class="text-secondary">هذا الرقم مربوط بمساحة عمل المكتب. الرسائل الواردة ستُوجّه لهذا المكتب بواسطة Phone Number ID مع الحفاظ على عزل بيانات المكاتب.</p>
            @else
                <h3 class="card-title">ربط رقم واتساب المكتب الحالي</h3>
                <p class="text-secondary">سيستخدم الربط الرسمي WhatsApp Business Platform / Coexistence. لا نستخدم QR أو مكتبات WhatsApp Web غير الرسمية.</p>
                <button class="btn btn-success" {{ $embeddedSignupReady ? '' : 'disabled' }}>
                    ربط واتساب المكتب
                </button>
                @unless($embeddedSignupReady)
                    <div class="text-secondary mt-2">زر الربط جاهز في المنتج، ويُفعّل بعد إضافة Meta App ID وEmbedded Signup Configuration ID على بيئة التشغيل.</div>
                @endunless
            @endif
        </div>
    </div>
</div>
@endsection
