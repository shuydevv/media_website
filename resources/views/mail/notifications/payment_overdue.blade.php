@extends('mail.layout')

@section('title', 'Доступ приостановлен')

@section('preheader')
Оплата за курс «{{ $courseTitle }}» просрочена, доступ приостановлен
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте, {{ $studentName ?? 'ученик' }}!</p>

<p style="margin:0 0 24px;">
  Оплата за курс <strong>«{{ $courseTitle }}»</strong> просрочена, доступ к курсу
  временно приостановлен.
</p>

@if($actionUrl)
  @include('mail.partials.button', ['url' => $actionUrl, 'label' => 'Оплатить и вернуть доступ →'])
@endif
@endsection
