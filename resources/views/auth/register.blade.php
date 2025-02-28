@extends('layouts.main')

@section('content')
<div class="container">
    <div class="flex justify-center mt-10">
        <div>
            <div>
                <div class="mb-6 text-2xl text-center">{{ __('Регистрация') }}</div>

                <div class="card-body border rounded p-8 pl-6 pr-8 w-96">
                    <form method="POST" action="{{ route('register') }}">
                        @csrf

                        <div class="">
                            <label for="name" class="">{{ __('Имя') }}</label>

                            <div class="col-md-6">
                                <input id="name" type="text" class="mt-1 bg-zinc-100 p-2 px-3 w-full form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                                @error('name')
                                    <span class="invalid-feedback text-sm text-red-500 mt-2" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="email" class=" mb-2">{{ __('Email') }}</label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="mt-1 bg-zinc-100 p-2 px-3 w-full form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">

                                @error('email')
                                    <span class="invalid-feedback text-sm text-red-500 mt-2" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="password" class="col-md-4 col-form-label text-md-end">{{ __('Введите пароль') }}</label>

                            <div class="col-md-6">
                                <input id="password" type="password" class="mt-1 bg-zinc-100 p-2 px-3 w-full form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">

                                @error('password')
                                    <span class="invalid-feedback text-sm text-red-500 mt-2" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="password-confirm" class="col-md-4 col-form-label text-md-end">{{ __('Подтвердите пароль') }}</label>

                            <div class="col-md-6">
                                <input id="password-confirm" type="password" class="mt-1 bg-zinc-100 p-2 px-3 w-full form-control" name="password_confirmation" required autocomplete="new-password">
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="mb-4 mt-8 flex py-3 w-100 w-full justify-center bg-zinc-900 text-white">
                                    {{ __('Зарегистрироваться') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <div class="mt-4 mb-12 text-center">У вас уже есть аккаунт? <a class="underline" href="{{route('login')}}">Войдите в него!</a></div>

        </div>
    </div>
</div>
@endsection
