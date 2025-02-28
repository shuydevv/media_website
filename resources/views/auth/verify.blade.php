@extends('layouts.main')

@section('content')
<div class="container">
    <div class="flex justify-center mt-10">
        <div class="col-md-8">
            <div class="card-body border rounded p-8 md:px-6 px-2 max-w-xl mx-2">
                <div class="text-2xl text-center">{{ __('Подтвердите вашу электронную почту') }}</div>
                <div class="card-body">

                    <div class="mt-4 text-center">
                        {{ __('Перед тем, как продолжить, перейдите по ссылке в письме, отправленном на вашу почту:') }}
                        {{Auth::user()->email}}
                    </div>
                    <div class="mt-12 text-center">
                    {{ __('Если вы не получили письмо, проверьте папку "спам" или отправьте письмо снова') }}
                    </div>
                    <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
                        @csrf
                        <div>
                            <button type="submit" class=" mt-4 flex py-3 w-100 w-full justify-center bg-zinc-900 text-white">{{ __('Отправить письмо еще раз') }}</button>
                        </div>
                        @if (session('resent'))
                        <div class="alert alert-success text-center mt-5 text-green-600" role="alert">
                            {{ __('Письмо с новой ссылкой было отправлено на вашу почту') }}

                            {{-- Вывести переменную с содержанием почты --}}
                        </div>
                    @endif
                    </form>
                </div>
            </div>

        </div>

    </div>
    
    <form action="{{route('logout')}}" method="post">
        @csrf
        <div class=" mt-10 text-zinc-400">
            <p class="text-center">Нет доступа к почте? Вы можете выйти из аккаунта и зарегистрироваться на другую почту</p>
            <button type="submit" class="mt-4 hover:opacity-50 block ml-auto mr-auto text-center border-b-2 ">Выйти из аккаунта</button>
        </div>
    </form>


</div>
@endsection
