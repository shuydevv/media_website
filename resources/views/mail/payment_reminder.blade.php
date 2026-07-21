@extends('mail.layout')

@section('title', 'Скоро оплата за курс')

@section('preheader')
Скоро наступит дата оплаты за курс «{{ $courseTitle }}» — {{ $dueAt->format('d.m.Y') }}
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте, {{ $studentName ?? 'ученик' }}!</p>

<p style="margin:0 0 16px;">
  Скоро наступит дата оплаты за курс <strong>«{{ $courseTitle }}»</strong> —
  {{ $dueAt->format('d.m.Y') }}.
</p>

<p style="margin:0;">Пожалуйста, оплатите вовремя, чтобы доступ к курсу не был приостановлен.</p>
@endsection
