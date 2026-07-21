@extends('mail.layout')

@section('title', 'Подтвердите e-mail')

@section('preheader')
Ваш код подтверждения: {{ $code }}
@endsection

@section('content')
<p style="margin:0 0 16px;">Привет!</p>

<p style="margin:0 0 12px;">Ваш код подтверждения:</p>

<p class="highlight-box" style="margin:0 0 20px;padding:14px 18px;font-size:24px;font-weight:650;letter-spacing:.12em;text-align:center;">
  {{ $code }}
</p>

<p style="margin:0 0 24px;">Код действует 15 минут. Либо нажмите кнопку ниже — она подтвердит e-mail сама, без ввода кода:</p>

@include('mail.partials.button', ['url' => $url, 'label' => 'Подтвердить e-mail →'])

<p style="margin:24px 0 0;">Если вы не запрашивали регистрацию, просто проигнорируйте это письмо.</p>
@endsection
