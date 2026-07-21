@extends('mail.layout')

@section('title', 'Обещанный платёж скоро истекает')

@section('preheader')
Доступ к «{{ $courseTitle }}» по обещанному платежу истекает {{ $expiresAt->format('d.m.Y H:i') }}
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте, {{ $studentName ?? 'ученик' }}!</p>

<p style="margin:0 0 24px;">
  Доступ к курсу <strong>«{{ $courseTitle }}»</strong> по обещанному платежу
  истекает <strong>{{ $expiresAt->format('d.m.Y H:i') }}</strong>. Чтобы не потерять доступ,
  оплатите курс до этого момента.
</p>

@if($actionUrl)
  @include('mail.partials.button', ['url' => $actionUrl, 'label' => 'Оплатить курс →'])
@endif
@endsection
