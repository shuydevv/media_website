@extends('mail.layout')

@section('title', 'Появилась запись урока')

@section('preheader')
Запись урока «{{ $lessonTitle }}» уже доступна
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте, {{ $studentName ?? 'ученик' }}!</p>

<p style="margin:0 0 24px;">Появилась запись урока <strong>«{{ $lessonTitle }}»</strong> — уже можно посмотреть.</p>

@if($actionUrl)
  @include('mail.partials.button', ['url' => $actionUrl, 'label' => 'Смотреть запись →'])
@endif
@endsection
