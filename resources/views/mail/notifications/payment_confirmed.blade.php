@extends('mail.layout')

@section('title', 'Платёж зафиксирован')

@section('preheader')
Мы получили вашу оплату за курс «{{ $courseTitle }}» на сумму {{ $amountRub }} ₽
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте, {{ $studentName ?? 'ученик' }}!</p>

<p style="margin:0 0 24px;">
  Мы получили вашу оплату за курс <strong>«{{ $courseTitle }}»</strong> на сумму
  <strong>{{ $amountRub }} ₽</strong>. Спасибо!
</p>

@if($actionUrl)
  @include('mail.partials.button', ['url' => $actionUrl, 'label' => 'Мои оплаты →'])
@endif
@endsection
