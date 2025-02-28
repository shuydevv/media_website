@extends('layouts.main')

@section('content')
<div class="container">
    <div class="flex justify-center mt-10">
        <div>
            <div>
                <div class="mb-6 text-2xl text-center">{{ __('Вход в аккаунт') }}</div>

                <div class="card-body border rounded p-8 pl-6 pr-8 w-96">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="">
                            <label for="email" class="">{{ __('Логин (Email)') }}</label>

                            <div class="col-md-6">
                                <input id="email" type="email" placeholder="mail@mail.com" class="mt-1 bg-zinc-100 p-2 px-3 w-full  @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

                                @error('email')
                                    <span class="" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="password" class=" mb-2">{{ __('Пароль') }}</label>

                            <div class="">
                                <input id="password" type="password" placeholder="Введите пароль" class="mt-1 bg-zinc-100 p-2 px-3 w-full form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

                                @error('password')
                                    <span class="" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="">
                            <div class="">
                                <div class="mt-4">
                                    <input class="" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>

                                    <label class="" for="remember">
                                        {{ __('Запомнить меня') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="">
                            <div class="">
                                <button type="submit" class="mb-4 mt-8 flex py-3 w-100 w-full justify-center bg-zinc-900 text-white">
                                    {{ __('Войти') }}
                                </button>

                                @if (Route::has('password.request'))
                                    <a class="btn btn-link flex justify-center" href="{{ route('password.request') }}">
                                        {{ __('Забыли пароль?') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <div class="mt-4 text-center">У вас еще нет аккаунта? <a class="underline" href="{{route('register')}}">Зарегистрируйтесь!</a></div>
        </div>
    </div>
</div>
@endsection
