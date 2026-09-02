@extends('mail.layout')

@section('title', 'Доступ к личному кабинету')

@section('preheader')
Войдите в личный кабинет одним нажатием и придумайте пароль
@endsection

@section('content')
<p style="margin:0 0 16px;">Здравствуйте!</p>

<p style="margin:0 0 24px;">Для вас создан доступ к личному кабинету. Войдите в кабинет по кнопке ниже. Останется только придумать пароль для входа в будущем.</p>

@include('mail.partials.button', ['url' => $loginUrl, 'label' => 'Войти в кабинет →'])

<p style="margin:24px 0 0;font-size:13px;color:#71717a;">Ссылка действует {{ $daysValid }} дней. После того как вы придумаете пароль, она станет недействительной — дальше входите обычным способом.</p>
@endsection
