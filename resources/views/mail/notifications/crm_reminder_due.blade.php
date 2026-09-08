@extends('mail.layout')

@section('title', 'Напоминание по ученику')

@section('preheader')
Напоминание по ученику {{ $studentName }}: {{ $note }}
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте!</p>

<p style="margin:0 0 24px;">
  Наступило напоминание, которое вы поставили себе в CRM по ученику
  <strong>{{ $studentName }}</strong> (на {{ $dueAt->format('d.m.Y') }}):
</p>

<p style="margin:0 0 24px;">«{{ $note }}»</p>

@if($actionUrl)
  @include('mail.partials.button', ['url' => $actionUrl, 'label' => 'Открыть карточку ученика →'])
@endif
@endsection
