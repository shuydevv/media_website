@extends('mail.layout')

@section('title', 'Урок скоро начнётся')

@section('preheader')
Урок по курсу «{{ $courseTitle }}» начнётся в {{ $startAt->format('H:i') }}
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте, {{ $studentName ?? 'ученик' }}!</p>

<p style="margin:0 0 24px;">
  Урок по курсу <strong>«{{ $courseTitle }}»</strong> скоро начнётся —
  в <strong>{{ $startAt->format('H:i') }}</strong>.
</p>

@if($actionUrl)
  @include('mail.partials.button', ['url' => $actionUrl, 'label' => 'Перейти к уроку →'])
@endif
@endsection
