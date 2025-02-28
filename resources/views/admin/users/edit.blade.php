@extends('admin.layouts.main')

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Редактировать пользователя</h1>
        <form action="{{ route('admin.user.update', $user->id) }}" method="post">
            @csrf
            @method('PATCH')
            <div>
                <label class="text-zinc-800 text-sm">Имя пользователя</label>
                <input class="p-2 block border" type="text" value="{{$user->name}}" name="name">
            </div>
            @error('name')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror

            <div>
                <label class="text-zinc-800 text-sm">Email</label>
                <input class="p-2 block border" type="text" value="{{$user->email}}" name="email">
            </div>
            @error('name')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror

            <div class="mt-5">
                <label class="mr-5">Выберите роль</label>
                <select name="role">
                    @foreach ($roles as $id => $role)
                        <option value="{{$id}}" {{ $id == $user->role ? ' selected' : '' }} >{{$role}}</option>
                    @endforeach
                    {{-- <option value="">История</option>
                    <option value="">Обществознание</option> --}}
                </select>
                @error('name')
                <p class="mt-2 text-red-400">*{{ $message }}</p>
                @enderror
            </div>

            <div>
                <input type="hidden" name="user_id" value="{{ $user->id }}">
            </div>

            <a><button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Опубликовать изменения</button></a>
        </form>
        


@endsection
