@extends('mail.layout')

@section('title', 'Сброс пароля')

@section('preheader')
Вы запросили ссылку для сброса пароля
@endsection

@section('content')
<p style="margin:0 0 16px;">Привет!</p>

<p style="margin:0 0 24px;">Вы запросили ссылку для сброса пароля. Если это были не вы — просто проигнорируйте это письмо, пароль останется прежним.</p>

@include('mail.partials.button', ['url' => $url, 'label' => 'Сбросить пароль →'])
@endsection
