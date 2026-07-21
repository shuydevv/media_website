@extends('mail.layout')

@section('title', 'Зачисление на курс')

@section('preheader')
Вы зачислены на курс «{{ $courseTitle }}»
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте, {{ $studentName ?? 'ученик' }}!</p>

<p style="margin:0 0 24px;">Вы зачислены на курс <strong>«{{ $courseTitle }}»</strong>.</p>

@if($actionUrl)
  @include('mail.partials.button', ['url' => $actionUrl, 'label' => 'Перейти к курсу →'])
@endif
@endsection
