@extends('mail.layout')

@section('title', 'Пароль для входа')

@section('preheader')
Ваш пароль для входа в личный кабинет
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте!</p>

<p style="margin:0 0 20px;">Для вас создан доступ к личному кабинету. Ваш пароль:</p>

<p class="highlight-box" style="margin:0 0 24px;padding:14px 18px;font-size:18px;font-weight:650;letter-spacing:.02em;text-align:center;">
  {{ $password }}
</p>

@include('mail.partials.button', ['url' => $loginUrl, 'label' => 'Перейти к входу →'])
@endsection
