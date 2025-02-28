@extends('admin.layouts.main')

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Создать пользователя</h1>
        <form action="{{route('admin.user.store')}}" method="post">
            @csrf

            <div class="mb-3">
                <label class="text-zinc-800 text-sm">Имя пользователя</label>
                <input class="p-2 block border" type="text" placeholder="Введите имя" name="name">
            </div>
            @error('name')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror

            <div class="mb-3">
                <label class="text-zinc-800 text-sm">Email</label>
                <input class="p-2 block border" type="text" placeholder="Введите почту" name="email">
            </div>
            @error('email')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror

            {{-- <div class="mb-3">
                <label class="text-zinc-800 text-sm">Пароль</label>
                <input class="p-2 block border" type="text" placeholder="Введите пароль" name="password">
            </div>
            @error('password')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror --}}

            <div class="mt-5">
                <label class="mr-5">Выберите роль</label>
                <select name="role">
                    @foreach ($roles as $id => $role)
                        <option value="{{$id}}">{{$role}}</option>
                    @endforeach
                    {{-- <option value="">История</option>
                    <option value="">Обществознание</option> --}}
                </select>
            </div>


            <a><button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Создать пользователя</button></a>
        </form>
        


@endsection
