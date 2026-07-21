@extends('mail.layout')

@section('title', 'Домашняя работа проверена')

@section('preheader')
Наставник проверил(а) вашу работу: «{{ $assignmentTitle }}»
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте, {{ $studentName ?? 'ученик' }}!</p>

<p style="margin:0 0 24px;">Ваша работа по теме <strong>«{{ $assignmentTitle }}»</strong> проверена.</p>

@if($linkToResult)
  @include('mail.partials.button', ['url' => $linkToResult, 'label' => 'Посмотреть результат →'])
@endif
@endsection
