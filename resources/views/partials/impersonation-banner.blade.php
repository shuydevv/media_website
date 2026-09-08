@if(session('impersonator_id'))
    <div class="w-full bg-indigo-600 text-white sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 py-2 flex items-center justify-between gap-3 text-sm">
            <span>Вы вошли как ученик «{{ auth()->user()->name }}» от имени администратора {{ session('impersonator_name') }}.</span>
            <form method="POST" action="{{ route('impersonate.leave') }}" class="shrink-0">
                @csrf
                <button type="submit" class="underline font-medium">Вернуться в аккаунт администратора</button>
            </form>
        </div>
    </div>
@endif
