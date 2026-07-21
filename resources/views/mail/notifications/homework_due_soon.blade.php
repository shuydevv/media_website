@extends('mail.layout')

@section('title', 'Скоро дедлайн домашки')

@section('preheader')
«{{ $homeworkTitle }}» — сдать нужно до {{ $dueAt->format('d.m.Y H:i') }}
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте, {{ $studentName ?? 'ученик' }}!</p>

<p style="margin:0 0 24px;">
  Скоро дедлайн домашнего задания <strong>«{{ $homeworkTitle }}»</strong> —
  сдать нужно до <strong>{{ $dueAt->format('d.m.Y H:i') }}</strong>.
</p>

@if($actionUrl)
  @include('mail.partials.button', ['url' => $actionUrl, 'label' => 'Перейти к домашке →'])
@endif
@endsection
